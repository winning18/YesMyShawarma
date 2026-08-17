<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentConfirmationService
{
    /**
     * POS momo orders may be placed without a transaction ID (staff skips
     * it during a rush — see PlaceOrderData::$paymentReference) and stay
     * payment_status 'pending' until this is called, entering the ID once
     * things settle down. Deliberately doesn't touch order.status — the
     * kitchen already started on the order at placement, same as cash;
     * this only ever resolves the payment-reconciliation bookkeeping.
     */
    public function confirmMomo(Order $order, string $transactionId, string $actorType, int $actorId, ?int $shiftId = null): Order
    {
        if ($order->payment_method !== 'momo') {
            throw PaymentException::wrongPaymentMethod('momo', $order->payment_method);
        }

        if ($order->payment_status === 'paid') {
            return $order;
        }

        return DB::transaction(function () use ($order, $transactionId, $actorType, $actorId, $shiftId) {
            $payment = $order->payments()->where('provider', 'momo')->lockForUpdate()->firstOrFail();

            // provider_reference is unique across all payments (payments.md
            // — it's the webhook idempotency key for Paystack, and doubles
            // as this uniqueness check for momo) — catch a fat-fingered or
            // reused transaction ID here rather than a raw constraint
            // violation surfacing as a 500.
            if (Payment::where('provider_reference', $transactionId)->where('id', '!=', $payment->id)->exists()) {
                throw PaymentException::duplicateTransactionReference($transactionId);
            }

            $payment->update([
                'provider_reference' => $transactionId,
                'status' => 'paid',
                'verified_at' => now(),
            ]);

            $order->payment_status = 'paid';
            $order->save();

            $order->events()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'shift_id' => $shiftId,
                'meta' => ['action' => 'momo_transaction_confirmed', 'transaction_id' => $transactionId],
            ]);

            return $order->refresh();
        });
    }
}
