<?php

namespace App\Services\Orders;

use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Services\Delivery\DeliveryFeeCalculator;
use Illuminate\Support\Facades\DB;

class OrderStateMachine
{
    public function __construct(private readonly DeliveryFeeCalculator $feeCalculator) {}

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

        $from = $order->status;

        if (! $this->canTransition($from, $to)) {
            throw InvalidOrderTransitionException::forTransition($from, $to);
        }

        if ($to === 'cancelled' && ! $cancellationReason) {
            throw InvalidOrderTransitionException::missingCancellationReason();
        }

        return DB::transaction(function () use ($order, $from, $to, $actorType, $actorId, $meta, $cancellationReason, $shiftId) {
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

                    // The cash payment row was created at placement with
                    // the fee-less total (delivery is cash-only — see
                    // OrderCreationService::create()) — keep it in sync
                    // with what's actually collected at the door.
                    $order->payments()->where('provider', 'cash')->update(['amount' => $order->total]);
                }
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

            // Deferred to the outermost transaction's commit — callers such
            // as ProcessPaystackWebhook wrap this in their own transaction,
            // and a queued broadcast job must never risk running before
            // that outer transaction has actually committed.
            $orderId = $order->id;
            $branchId = $order->branch_id;
            $trackToken = $order->track_token;
            DB::afterCommit(fn () => OrderStatusChanged::dispatch($orderId, $branchId, $to, $trackToken));

            return $order->refresh();
        });
    }
}
