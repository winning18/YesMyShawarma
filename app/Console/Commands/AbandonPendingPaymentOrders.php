<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * "abandoned | No payment within 30 minutes | scheduled job" — orders.md
 * defines the transition but, like escalation, requires an actual scheduled
 * scan rather than a delayed queued job (lost on worker restart).
 */
#[Signature('orders:abandon-pending-payment')]
#[Description('Transition pending_payment orders older than 30 minutes to abandoned')]
class AbandonPendingPaymentOrders extends Command
{
    private const THRESHOLD_MINUTES = 30;

    public function handle(OrderStateMachine $stateMachine): int
    {
        Order::where('status', 'pending_payment')
            ->where('placed_at', '<=', now()->subMinutes(self::THRESHOLD_MINUTES))
            ->each(fn (Order $order) => $stateMachine->transition($order, 'abandoned', 'system'));

        return self::SUCCESS;
    }
}
