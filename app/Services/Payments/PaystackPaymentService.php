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
     * placeholder only satisfies that API contract — .invalid is the
     * RFC 2606 reserved TLD for addresses that must never resolve.
     */
    private function placeholderEmail(Customer $customer): string
    {
        return "customer-{$customer->id}@guests.yesmyshawarma.invalid";
    }
}
