<?php

namespace App\Services\Payments;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\PaymentException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaystackPaymentService
{
    public function __construct(
        private readonly PaystackClient $client,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    /**
     * Order creation (OrderCreationService) leaves `pending_payment` orders
     * with no `payments` row — that row, and the provider_reference that
     * becomes the webhook's idempotency key, are only created once a
     * Paystack transaction is actually initialised, here.
     *
     * Web checkout only. POS's 'momo' is an in-house manual payment (staff
     * confirm the customer sent it) — it never reaches this method; see
     * Order::MANUALLY_SETTLED_PAYMENT_METHODS and PosController::store().
     *
     * @return array{authorization_url: string, reference: string}
     */
    public function initializeForOrder(Order $order, string $callbackUrl): array
    {
        if ($order->payment_method !== 'paystack') {
            throw PaymentException::wrongPaymentMethod('paystack', $order->payment_method);
        }

        // Each attempt gets its own reference — a customer may retry a
        // failed or abandoned payment, and payments is a hasMany on Order
        // precisely to support more than one attempt per order.
        $reference = $order->reference.'-'.strtoupper(Str::random(8));

        $response = $this->client->initializeTransaction([
            'email' => $order->customer->email ?: $this->placeholderEmail($order->customer),
            'amount' => $order->total,
            'currency' => 'GHS',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'order_id' => $order->id,
                'order_reference' => $order->reference,
            ],
        ]);

        $order->payments()->create([
            'provider' => 'paystack',
            'provider_reference' => $reference,
            'amount' => $order->total,
            'currency' => 'GHS',
            'status' => 'pending',
            'raw_payload' => $response,
        ]);

        return [
            'authorization_url' => $response['data']['authorization_url'],
            'reference' => $reference,
        ];
    }

    /**
     * The single place a Paystack payment actually gets confirmed — called
     * both by the webhook job (ProcessPaystackWebhook) and by
     * CheckoutController::paystackReturn()'s narrow verify-on-return
     * exception (payments.md: "The webhook is the only source of truth").
     * Idempotent and lock-guarded so whichever of the two gets here first
     * does the real work; the other is a safe no-op — never two
     * implementations that could drift or race against each other.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function confirmPayment(string $reference, int $amountReceived, array $rawPayload): void
    {
        $payment = Payment::where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->first();

        if (! $payment) {
            Log::error('Paystack confirmation for unknown payment reference', ['reference' => $reference]);

            return;
        }

        DB::transaction(function () use ($payment, $amountReceived, $rawPayload) {
            // Lock before the idempotency check, not after — see
            // ProcessPaystackWebhook's original docblock for why: two
            // concurrent confirmations for the same reference (a webhook
            // retry racing the customer's own browser return, say) must
            // never both read status !== 'paid' before either commits.
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid') {
                return;
            }

            // Store the raw payload before acting on it, regardless of what
            // happens next — per payments.md, it's the only record that
            // matters if a dispute arrives later.
            $payment->update(['raw_payload' => $rawPayload]);

            $order = $payment->order;

            if ($amountReceived !== $order->total) {
                Log::critical('Paystack payment amount mismatch — order left in pending_payment', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'expected_total' => $order->total,
                    'amount_received' => $amountReceived,
                ]);

                $payment->update(['status' => 'amount_mismatch']);

                return;
            }

            $payment->update(['status' => 'paid', 'verified_at' => now()]);

            try {
                $this->stateMachine->transition($order, 'paid', 'system');
            } catch (InvalidOrderTransitionException $e) {
                Log::critical('Paystack payment could not transition order to paid', [
                    'order_id' => $order->id,
                    'current_status' => $order->status,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            $order->update(['payment_status' => 'paid']);
        });
    }

    /**
     * Paystack's initialize endpoint requires an email; customers here are
     * phone-first and email is optional (CLAUDE.md's identity model). This
     * placeholder only satisfies that API contract.
     *
     * Was originally "@guests.yesmyshawarma.invalid" — RFC 2606's reserved
     * TLD for addresses that must never resolve, the semantically correct
     * choice on paper. Confirmed live against Paystack (test keys, real
     * API call, not a guess): they reject it outright with "Invalid Email
     * Address Passed", 400. Paystack validates the TLD is a real one, not
     * just the address shape — a subdomain of a domain we actually own
     * satisfies that without needing a real mailbox behind it. Any receipt
     * Paystack sends here bounces silently, which is fine: nothing in this
     * app depends on that email arriving, order confirmation is our own
     * SMS/tracking-page flow regardless of payment method.
     */
    private function placeholderEmail(Customer $customer): string
    {
        return "customer-{$customer->id}@guests.yesmyshawarma.com";
    }
}
