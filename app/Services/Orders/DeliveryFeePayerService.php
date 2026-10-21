<?php

namespace App\Services\Orders;

use App\Exceptions\DeliveryFeePayerException;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;

/**
 * Changes how a POS MoMo delivery order's delivery fee is paid — by MoMo
 * with the food, or cash to the rider (orders.delivery_fee_paid_by) — for
 * when the customer changes their mind after the order was placed. Decides
 * what the rider collects at the door (OrderResource) and whether the fee
 * counts as from the customer or from staff (DeliveryFeeReportService).
 */
class DeliveryFeePayerService
{
    private const INELIGIBLE_STATUSES = ['delivered', 'failed', 'cancelled', 'rejected', 'refunded', 'abandoned'];

    /**
     * @throws DeliveryFeePayerException
     */
    public function set(Order $order, string $paidBy, string $actorType, int $actorId, ?int $shiftId = null): Order
    {
        return DB::transaction(function () use ($order, $paidBy, $actorType, $actorId, $shiftId) {
            // Same lock re-fetch as DeliveryFeeAdjustmentService::adjust().
            $locked = Order::withoutGlobalScope(BranchScope::class)
                ->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->setRawAttributes($locked->getAttributes(), true);

            if ($order->payment_method !== 'momo' || $order->fulfilment_type !== 'delivery') {
                throw DeliveryFeePayerException::notMomoDelivery();
            }

            if (in_array($order->status, self::INELIGIBLE_STATUSES, true)) {
                throw DeliveryFeePayerException::notEligible($order->status);
            }

            $old = $order->delivery_fee_paid_by ?? 'momo';

            if ($old === $paidBy) {
                return $order;
            }

            $order->delivery_fee_paid_by = $paidBy;
            $order->save();

            $order->events()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'shift_id' => $shiftId,
                'meta' => ['action' => 'delivery_fee_paid_by_changed', 'from' => $old, 'to' => $paidBy],
            ]);

            return $order->refresh();
        });
    }
}
