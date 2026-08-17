<?php

namespace App\Jobs;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Payment;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaystackWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function handle(OrderStateMachine $stateMachine): void
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

        $payment = Payment::where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->first();

        if (! $payment) {
            Log::error('Paystack webhook for unknown payment reference', ['reference' => $reference]);

            return;
        }

        DB::transaction(function () use ($payment, $data, $stateMachine) {
            // Lock the row before the idempotency check, not after — Paystack
            // retries webhook delivery, and without the lock two concurrent
            // deliveries for the same reference can both read status !==
            // 'paid' before either commits, each proceeding to transition
            // the order a second time.
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid') {
                return;
            }

            // Store the raw payload before acting on it, regardless of what
            // happens next — per payments.md, it's the only record that
            // matters if a dispute arrives later.
            $payment->update(['raw_payload' => $this->payload]);

            $order = $payment->order;
            $amountReceived = (int) ($data['amount'] ?? 0);

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
                $stateMachine->transition($order, 'paid', 'system');
            } catch (InvalidOrderTransitionException $e) {
                Log::critical('Paystack webhook could not transition order to paid', [
                    'order_id' => $order->id,
                    'current_status' => $order->status,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            $order->update(['payment_status' => 'paid']);
        });
    }
}
