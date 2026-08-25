<?php

namespace App\Jobs;

use App\Services\Payments\PaystackPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPaystackWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function handle(PaystackPaymentService $payments): void
    {
        if (($this->payload['event'] ?? null) !== 'charge.success') {
            return;
        }

        $data = $this->payload['data'] ?? [];
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            Log::error('Paystack webhook payload missing transaction reference', ['payload' => $this->payload]);

            return;
        }

        $payments->confirmPayment($reference, (int) ($data['amount'] ?? 0), $this->payload);
    }
}
