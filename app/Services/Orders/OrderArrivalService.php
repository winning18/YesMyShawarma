<?php

namespace App\Services\Orders;

use App\Exceptions\OrderArrivalException;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\Delivery\DeliveryFeeCalculator;
use Illuminate\Support\Facades\DB;

/**
 * "Arrived" isn't a new order status — the state machine stays dispatched
 * -> delivered/failed exactly as orders.md documents. It's a one-time,
 * rider-only bookkeeping event (arrived_at, same denormalised-timestamp
 * pattern as dispatched_at/delivered_at) that gates the rider's own "Mark
 * delivered" button and, when the customer never shared their location at
 * checkout, is the moment delivery_fee finally gets calculated — from the
 * rider's own position now that they're actually at the door, rather than
 * guessed at placement and corrected later.
 */
class OrderArrivalService
{
    public function __construct(
        private readonly DeliveryFeeCalculator $feeCalculator,
    ) {}

    public function arrive(Order $order, User $rider, ?float $lat, ?float $lng): Order
    {
        return DB::transaction(function () use ($order, $rider, $lat, $lng) {
            // withoutGlobalScope — same reasoning as RefundService::complete()
            // and OrderStateMachine::transition()'s own lock re-fetch: $order
            // is the specific row already resolved and authorized (rider_id
            // === $rider->id, checked above/in the policy), not a fresh
            // permission check — re-scoping to the rider's ambient session
            // branch would 404 them the moment it differs from this order's,
            // which is routine for a rider holding the role at more than one
            // branch.
            $locked = Order::withoutGlobalScope(BranchScope::class)
                ->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->setRawAttributes($locked->getAttributes(), true);

            if ($order->fulfilment_type !== 'delivery') {
                throw OrderArrivalException::notADeliveryOrder();
            }

            if ($order->status !== 'dispatched') {
                throw OrderArrivalException::notEligible($order->status);
            }

            if ($order->arrived_at !== null) {
                throw OrderArrivalException::alreadyArrived();
            }

            $feeCalculated = false;

            // Only when nothing has priced it yet — a real checkout-time
            // location, or a manual correction via DeliveryFeeAdjustmentService,
            // both already leave delivery_fee above 0, and arrival must
            // never silently overwrite either.
            if ($order->delivery_fee === 0) {
                $newFee = $lat !== null && $lng !== null
                    ? $this->feeCalculator->calculate($order->branch, $lat, $lng)
                    : DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS;

                $order->delivery_fee = $newFee;
                $order->total = $order->subtotal - $order->discount_total + $newFee;
                $order->payments()->whereIn('provider', Order::MANUALLY_SETTLED_PAYMENT_METHODS)->update(['amount' => $order->total]);
                $feeCalculated = true;
            }

            $order->arrived_at = now();
            $order->save();

            $order->events()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'actor_type' => 'rider',
                'actor_id' => $rider->id,
                'meta' => [
                    'action' => 'arrived',
                    'lat' => $lat,
                    'lng' => $lng,
                    'delivery_fee_calculated' => $feeCalculated,
                    'delivery_fee' => $order->delivery_fee,
                ],
            ]);

            return $order->refresh();
        });
    }
}
