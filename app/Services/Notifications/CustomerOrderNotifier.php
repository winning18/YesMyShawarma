<?php

namespace App\Services\Notifications;

use App\Contracts\Notifier;
use App\Models\Order;
use App\Models\Refund;

/**
 * The four customer-facing order-status SMS points — see orders.md's state
 * machine for exactly which transitions each of these fires from
 * (OrderCreationService for "placed" when an order is created already-paid,
 * OrderStateMachine::transition() for everything else, including the
 * Paystack "placed" case). Kept as one small service, separate from the
 * transition/creation logic itself, so the message copy lives in exactly
 * one place — CLAUDE.md's "business logic lives in service classes" rule.
 */
class CustomerOrderNotifier
{
    public function __construct(private readonly Notifier $notifier) {}

    public function placed(Order $order): void
    {
        $this->send($order, "Thanks! We've received your order {$order->reference}.");
    }

    public function received(Order $order): void
    {
        $this->send($order, "Your order {$order->reference} has been accepted and is being prepared.");
    }

    public function readyForPickup(Order $order): void
    {
        $this->send($order, "Your order {$order->reference} is ready for pickup!");
    }

    public function dispatched(Order $order): void
    {
        $this->send($order, "Your order {$order->reference} is on its way!");
    }

    public function delivered(Order $order): void
    {
        $this->send($order, "Your order {$order->reference} has been delivered. Enjoy!");
    }

    public function refundProcessed(Refund $refund): void
    {
        $order = $refund->order;
        $amount = number_format($refund->amount / 100, 2);

        $this->notifier->notify(
            $order->customer->phone,
            "Your refund of GHS {$amount} for order {$order->reference} has been processed.",
            ['order_id' => $order->id, 'refund_id' => $refund->id],
        );
    }

    private function send(Order $order, string $message): void
    {
        $link = route('tracking.show', $order);

        $this->notifier->notify(
            $order->customer->phone,
            "{$message} Track it here: {$link}",
            ['order_id' => $order->id],
        );
    }
}
