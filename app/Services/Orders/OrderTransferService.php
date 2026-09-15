<?php

namespace App\Services\Orders;

use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Exceptions\OrderTransferException;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Support\SafeBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moving an order to a closer branch after placement — a customer picked
 * the wrong branch, or ordered for someone else the "nearest branch" logic
 * never had a chance to account for. Deliberately narrow: only while the
 * order is 'paid' or 'accepted', before any kitchen has actually started on
 * it (see orders.md's "Branch transfer" section) — a rider is never in the
 * picture either, since auto-assignment only fires once an order reaches
 * 'ready'.
 *
 * Money never gets rewritten on an order that's already been charged —
 * there's no way to re-run a completed Paystack transaction. A delivery-fee
 * decrease on an already-paid order goes back to the customer as a partial
 * refund through the existing refunds ledger; an increase is absorbed
 * (never charged) and simply recorded in the transfer's order_events row so
 * it's visible, not hidden. Orders where nothing has been collected yet
 * (cash/momo still pending) just get their total corrected directly —
 * there's nothing to refund.
 *
 * Staff holds this permission too (unlike void/refund/discount, which stay
 * manager-and-above), but transferring is never a back door around the
 * refund approval workflow: staff can move the order immediately, but a
 * resulting fee-decrease refund goes in as a pending request needing
 * manager/owner approval, exactly like every other refund staff touches —
 * see $canDirectlyRefund below. Only manager/general_manager/owner get the
 * one-click completed refund a transfer might trigger.
 */
class OrderTransferService
{
    public function __construct(
        private readonly DeliveryFeeCalculator $feeCalculator,
        private readonly RefundService $refunds,
    ) {}

    /**
     * Other branches this order could be transferred to — active, accepting
     * orders, not the one it's already at. Item availability at the
     * destination is deliberately not pre-checked here (that would mean
     * running this per order for every card on the board); transfer()
     * validates it at the point of action instead.
     *
     * @return Collection<int, Branch>
     */
    public function otherAcceptingBranches(int $excludingBranchId): Collection
    {
        return Branch::where('is_active', true)
            ->where('is_accepting_orders', true)
            ->where('id', '!=', $excludingBranchId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function transfer(Order $order, Branch $destination, User $actor, string $actorType, ?string $reason, ?int $shiftId = null, bool $canDirectlyRefund = false): Order
    {
        return DB::transaction(function () use ($order, $destination, $actor, $actorType, $reason, $shiftId, $canDirectlyRefund) {
            // Same reasoning as OrderStateMachine::transition() — lock and
            // sync onto the authoritative row before deciding anything, in
            // case a concurrent action (e.g. the kitchen just accepted it)
            // landed since $order was loaded.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->setRawAttributes($locked->getAttributes(), true);

            if (! in_array($order->status, ['paid', 'accepted'], true)) {
                throw OrderTransferException::notEligible($order->status);
            }

            if ($order->branch_id === $destination->id) {
                throw OrderTransferException::sameBranch();
            }

            if (! $destination->is_active || ! $destination->is_accepting_orders) {
                throw OrderTransferException::destinationNotAccepting();
            }

            $missing = $order->items
                ->reject(fn ($item) => $destination->menuItems()->find($item->menu_item_id)?->pivot?->is_available)
                ->pluck('name_snapshot')
                ->all();

            if ($missing !== []) {
                throw OrderTransferException::itemsUnavailable($missing);
            }

            $originBranchId = $order->branch_id;
            $oldFee = $order->delivery_fee;
            $newFee = $oldFee;

            if ($order->fulfilment_type === 'delivery') {
                $lat = $order->delivery_address_snapshot['lat'] ?? null;
                $lng = $order->delivery_address_snapshot['lng'] ?? null;

                if ($lat !== null && $lng !== null) {
                    $newFee = $this->feeCalculator->calculate($destination, (float) $lat, (float) $lng);
                }
            }

            $feeDelta = $newFee - $oldFee;

            if ($order->payment_status === 'paid') {
                // Already collected — never rewrite a locked total. A
                // decrease is handed back through the refunds ledger; an
                // increase is absorbed (recorded below, never charged).
                // Staff never gets the one-click completed refund here,
                // same boundary as every other refund path they touch —
                // see the class docblock.
                if ($feeDelta < 0) {
                    $amount = abs($feeDelta);
                    $reasonText = 'Delivery fee adjustment — order transferred to a closer branch.';

                    $canDirectlyRefund
                        ? $this->refunds->directRefund($order, $actor, $amount, $reasonText, $actorType, $shiftId)
                        : $this->refunds->request($order, $actor, $amount, $reasonText, $actorType, $shiftId);
                }
            } else {
                // Nothing collected yet (cash/momo still pending) — just
                // correct the total; whoever collects it collects the right
                // amount. Mirrors OrderStateMachine's own delivered-transition
                // fee reconciliation for manually-settled methods.
                $order->delivery_fee = $newFee;
                $order->total = $order->subtotal - $order->discount_total + $newFee;
                $order->payments()->whereIn('provider', Order::MANUALLY_SETTLED_PAYMENT_METHODS)->update(['amount' => $order->total]);
            }

            $order->branch_id = $destination->id;
            $order->save();

            $order->events()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'actor_type' => $actorType,
                'actor_id' => $actor->id,
                'shift_id' => $shiftId,
                'meta' => [
                    'action' => 'branch_transfer',
                    'from_branch_id' => $originBranchId,
                    'to_branch_id' => $destination->id,
                    'reason' => $reason,
                    'delivery_fee_old' => $oldFee,
                    'delivery_fee_new' => $newFee,
                    'delivery_fee_absorbed' => max(0, $feeDelta),
                ],
            ]);

            $orderId = $order->id;
            $status = $order->status;
            $trackToken = $order->track_token;
            $destinationBranchId = $destination->id;

            // Origin's board refetches and the order is simply gone from it
            // (BranchScope no longer matches); destination's board refetches
            // and picks it up fresh — 'paid' lands it back in "Needs
            // acknowledgement", 'accepted' in "In progress", same
            // categorisation the client already does off the order's own
            // status, not off which event fired.
            SafeBroadcast::afterCommit(fn () => OrderStatusChanged::dispatch($orderId, $originBranchId, $status, $trackToken));
            SafeBroadcast::afterCommit(fn () => OrderPlaced::dispatch($orderId, $destinationBranchId));

            return $order->refresh();
        });
    }
}
