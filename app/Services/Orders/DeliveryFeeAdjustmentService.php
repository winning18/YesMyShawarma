<?php

namespace App\Services\Orders;

use App\Exceptions\DeliveryFeeAdjustmentException;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sets or corrects the delivery fee for an order whose customer never
 * shared a location at checkout — normally left at 0 until the rider
 * marks the order arrived and it's calculated from their own position
 * (see OrderArrivalService and orders.md's "Delivery fee at arrival"
 * section), but staff can step in earlier or correct that figure once
 * they can see the landmark/area and judge it looks wrong.
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
            // withoutGlobalScope — same reasoning as RefundService::complete()
            // and OrderStateMachine::transition()'s own lock re-fetch: $order
            // is already resolved and authorized against its own branch, not
            // the actor's ambient session one.
            $locked = Order::withoutGlobalScope(BranchScope::class)
                ->whereKey($order->id)->lockForUpdate()->firstOrFail();
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
