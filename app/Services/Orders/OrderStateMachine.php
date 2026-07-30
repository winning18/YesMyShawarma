<?php

namespace App\Services\Orders;

use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderStateMachine
{
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
