<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentException;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Str;

class PaystackPaymentService
{
    public function __construct(private readonly PaystackClient $client) {}

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
