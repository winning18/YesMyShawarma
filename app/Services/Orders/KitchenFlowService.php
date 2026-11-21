<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Settings\SettingsService;

/**
 * Branches without a kitchen screen (owner, 2026-11-20; chosen per branch,
 * on the branch's page): nobody works the Orders board step by step, so
 * an order needs one tap, Ready.
 *
 * - A POS order is accepted as it's placed (the kitchen ticket still
 *   prints and stock is still used up), then waits, preparing, for Ready.
 * - A website or delivery order still has to be accepted by someone (it
 *   wasn't paid at a counter); accepted, it goes straight to preparing.
 * - Ready on a POS pickup or dine-in order completes it ("Done"): the
 *   customer is at the counter or the table. A delivery goes on to the
 *   rider as always; a website pickup waits for the customer to collect.
 *
 * Every automatic step is an ordinary transition, so it writes its own
 * order_events row (actor 'system', meta action 'no_kitchen_screen').
 */
class KitchenFlowService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function hasKitchenScreen(int $branchId): bool
    {
        return $this->settings->branchHasKitchenScreen($branchId);
    }

    /** One tap finishes this order on a branch without a kitchen screen. */
    public function finishesOnReady(Order $order): bool
    {
        return ! $this->hasKitchenScreen($order->branch_id)
            && $order->channel === 'pos'
            && $order->fulfilment_type !== 'delivery';
    }

    /** Called by OrderCreationService once a POS order is placed. */
    public function afterPlaced(Order $order, OrderStateMachine $orders, string $actorType, ?int $actorId): Order
    {
        if ($order->channel !== 'pos' || $order->status !== 'paid' || $this->hasKitchenScreen($order->branch_id)) {
            return $order;
        }

        return $orders->transition($order, 'accepted', $actorId ? $actorType : 'system', $actorId, ['action' => 'no_kitchen_screen'], shiftId: $order->shift_id);
    }

    /** Called by OrderStateMachine after every transition. */
    public function afterTransition(Order $order, string $to, OrderStateMachine $orders): void
    {
        if ($this->hasKitchenScreen($order->branch_id)) {
            return;
        }

        if ($to === 'accepted') {
            $orders->transition($order, 'preparing', 'system', null, ['action' => 'no_kitchen_screen']);
        } elseif ($to === 'ready' && $this->finishesOnReady($order)) {
            $orders->transition($order, 'dispatched', 'system', null, ['action' => 'no_kitchen_screen']);
        }
    }
}
