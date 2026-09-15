<?php

namespace App\Services\Orders;

use App\Exceptions\DeliveryFeeAdjustmentException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a flat, estimated delivery fee (OrderCreationService's fallback
 * when a customer's location wasn't captured — see
 * DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS) for a specific
 * address, once staff can see the landmark/area and judge that the flat
 * estimate looks wrong for this particular delivery.
 *
 * Deliberately narrower than OrderTransferService's money handling: this
 * fee was never charged through Paystack in the first place (only the
 * subtotal was, for exactly this reason — see OrderCreationService's
 * docblock), so there is nothing to refund or absorb here, ever. It's
 * purely a correction to what the rider is told to collect in cash.
 */
class DeliveryFeeAdjustmentService
{
    private const INELIGIBLE_STATUSES = ['delivered', 'failed', 'cancelled', 'rejected', 'refunded', 'abandoned'];

    public function adjust(Order $order, int $newFeePesewas, User $actor, string $actorType, ?string $reason, ?int $shiftId = null): Order
    {
        return DB::transaction(function () use ($order, $newFeePesewas, $actor, $actorType, $reason, $shiftId) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->setRawAttributes($locked->getAttributes(), true);

            if ($order->fulfilment_type !== 'delivery') {
                throw DeliveryFeeAdjustmentException::notDeliveryOrder();
            }

            if (in_array($order->status, self::INELIGIBLE_STATUSES, true)) {
                throw DeliveryFeeAdjustmentException::notEligible($order->status);
            }

            $lat = $order->delivery_address_snapshot['lat'] ?? null;
            $lng = $order->delivery_address_snapshot['lng'] ?? null;

            if ($lat !== null && $lng !== null) {
                throw DeliveryFeeAdjustmentException::preciselyPriced();
            }

            $oldFee = $order->delivery_fee;

            $order->delivery_fee = $newFeePesewas;
            $order->total = $order->subtotal - $order->discount_total + $newFeePesewas;

            // Never touches a 'paystack' payments row — that already
            // correctly reflects only the subtotal actually charged
            // online, same as everywhere else this distinction is made.
            $order->payments()->whereIn('provider', Order::MANUALLY_SETTLED_PAYMENT_METHODS)->update(['amount' => $order->total]);

            $order->save();

            $order->events()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'actor_type' => $actorType,
                'actor_id' => $actor->id,
                'shift_id' => $shiftId,
                'meta' => [
                    'action' => 'delivery_fee_adjusted',
                    'delivery_fee_old' => $oldFee,
                    'delivery_fee_new' => $newFeePesewas,
                    'reason' => $reason,
                ],
            ]);

            return $order->refresh();
        });
    }
}
