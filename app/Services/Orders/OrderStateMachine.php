<?php

namespace App\Services\Orders;

use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;

class OrderStateMachine
{
    public function __construct(
        private readonly DeliveryFeeCalculator $feeCalculator,
        private readonly RiderAssignmentService $riderAssignment,
    ) {}

    /**
     * Valid transitions, per .claude/rules/orders.md. "Cancelled" is only ever
     * reachable through accepted/preparing/ready, all of which sit downstream
     * of "paid" — so any cancellation through this graph is structurally a
     * cancellation "after paid", and the reason requirement below applies
     * unconditionally rather than needing to check payment history.
     *
     * rejected/cancelled -> refunded are not in the orders.md diagram itself
     * (drawn only from "failed"), but both states are only reachable after
     * "paid" — money was captured — so the same "triggers a refund decision"
     * language applies. Flagging this inference explicitly since it extends
     * what's drawn rather than just implementing it.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending_payment' => ['paid', 'abandoned'],
        'paid' => ['accepted', 'rejected'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered', 'failed'],
        'rejected' => ['refunded'],
        'cancelled' => ['refunded'],
        'failed' => ['refunded'],
    ];

    /**
     * The denormalised timestamp column stamped on `orders` for a given
     * status, where one exists. Statuses not listed here have no dedicated
     * column — order_events remains the source of truth for their timing.
     *
     * @var array<string, string>
     */
    private const TIMESTAMP_COLUMNS = [
        'accepted' => 'accepted_at',
        'ready' => 'ready_at',
        'dispatched' => 'dispatched_at',
        'delivered' => 'delivered_at',
        'cancelled' => 'cancelled_at',
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function transition(
        Order $order,
        string $to,
        string $actorType,
        ?int $actorId = null,
        array $meta = [],
        ?string $cancellationReason = null,
        ?int $shiftId = null,
    ): Order {
        if ($actorType !== 'system' && $actorId === null) {
            throw InvalidOrderTransitionException::missingActor();
        }

        return DB::transaction(function () use ($order, $to, $actorType, $actorId, $meta, $cancellationReason, $shiftId) {
            // Lock the row for the duration of the transaction and sync onto
            // the authoritative current status before deciding anything — a
            // concurrent transition (e.g. a duplicate webhook delivery
            // processed by another worker, or a double-clicked action) may
            // have already changed it since $order was loaded.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->setRawAttributes($locked->getAttributes(), true);

            $from = $order->status;

            if (! $this->canTransition($from, $to)) {
                throw InvalidOrderTransitionException::forTransition($from, $to);
            }

            if ($to === 'cancelled' && ! $cancellationReason) {
                throw InvalidOrderTransitionException::missingCancellationReason();
            }

            $order->status = $to;

            if ($column = self::TIMESTAMP_COLUMNS[$to] ?? null) {
                $order->{$column} = now();
            }

            if ($to === 'cancelled') {
                $order->cancellation_reason = $cancellationReason;
            }

            // OrderCreationService already prices delivery_fee immediately
            // when the customer's live location was captured at checkout —
            // this recomputes the same figure from the same snapshot
            // lat/lng, which is harmless (deterministic distance × rate).
            // What actually matters here is the other case: location wasn't
            // captured at checkout, delivery_fee is still 0, and this is
            // the first and only point it gets priced, now that the trip
            // has actually happened. Pickup orders and any delivery order
            // still missing lat/lng (geolocation was never captured) are
            // left at whatever fee they already have.
            if ($to === 'delivered' && $order->fulfilment_type === 'delivery') {
                $lat = $order->delivery_address_snapshot['lat'] ?? null;
                $lng = $order->delivery_address_snapshot['lng'] ?? null;

                if ($lat !== null && $lng !== null) {
                    $order->delivery_fee = $this->feeCalculator->calculate($order->branch, (float) $lat, (float) $lng);
                    $order->total = $order->subtotal - $order->discount_total + $order->delivery_fee;

                    // The cash/momo payment row was created at placement
                    // with the fee-less total (a manually-settled order —
                    // Order::MANUALLY_SETTLED_PAYMENT_METHODS — is the only
                    // case that reaches "delivered" with no known fee yet;
                    // see OrderCreationService::create()) — keep it in sync
                    // with what's actually collected at the door.
                    $order->payments()->whereIn('provider', Order::MANUALLY_SETTLED_PAYMENT_METHODS)->update(['amount' => $order->total]);
                }
            }

            // payments.md: cash collected at the door is reconciled the
            // moment the rider confirms they've got it, at the same instant
            // the order transitions to 'delivered' — a pickup+cash order is
            // already 'paid' from OrderCreationService, so this only ever
            // does something for delivery+cash. Deliberately momo-exclusive:
            // momo is reconciled by transaction ID (PaymentConfirmationService
            // ::confirmMomo()), not by delivery — flipping it here too would
            // mark a momo order "paid" with no transaction ID ever entered.
            if ($to === 'delivered' && $order->payment_method === 'cash') {
                $order->payment_status = 'paid';
            }

            $order->save();

            $order->events()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'shift_id' => $shiftId,
                'meta' => $meta,
            ]);

            // SafeBroadcast::afterCommit, not a bare DB::afterCommit — see
            // its own docblock. Deferred to the outermost transaction's
            // commit either way: callers such as ProcessPaystackWebhook
            // wrap this in their own transaction, and this must never risk
            // running before that outer transaction has actually committed.
            $orderId = $order->id;
            $branchId = $order->branch_id;
            $trackToken = $order->track_token;
            SafeBroadcast::afterCommit(fn () => OrderStatusChanged::dispatch($orderId, $branchId, $to, $trackToken));

            // Pickup orders reach "ready" too, but never need a rider
            // (orders.md: "Pickup orders skip the rider entirely"). Nobody
            // eligible is a normal outcome here, not an error — the order
            // just stays "ready" with rider_id null, for manual assignment.
            if ($to === 'ready' && $order->fulfilment_type === 'delivery') {
                $this->riderAssignment->autoAssign($order);
            }

            return $order->refresh();
        });
    }
}
