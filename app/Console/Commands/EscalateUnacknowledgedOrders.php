<?php

namespace App\Console\Commands;

use App\Contracts\Notifier;
use App\Models\Order;
use App\Services\Branches\BranchContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scans for `paid` orders sitting unacknowledged, per orders.md's escalation
 * rules. Deliberately a scheduled command, not a queued delayed job — a
 * delayed job is lost on worker restart, which is exactly when an
 * unacknowledged order most needs to be caught.
 */
#[Signature('orders:escalate-unacknowledged')]
#[Description('Escalate paid orders unacknowledged past 5/10 minute thresholds')]
class EscalateUnacknowledgedOrders extends Command
{
    /**
     * @var array<int, string>
     */
    private const THRESHOLDS = [
        5 => 'manager',
        10 => 'owner',
    ];

    public function handle(BranchContext $context, Notifier $notifier): int
    {
        $paidOrders = Order::where('status', 'paid')->with('events')->get();

        foreach ($paidOrders as $order) {
            $this->escalateIfDue($order, $context, $notifier);
        }

        return self::SUCCESS;
    }

    private function escalateIfDue(Order $order, BranchContext $context, Notifier $notifier): void
    {
        $paidAt = $order->events
            ->where('to_status', 'paid')
            ->sortByDesc('created_at')
            ->first()
            ?->created_at ?? $order->placed_at;

        if (! $paidAt) {
            return;
        }

        $minutesUnacknowledged = $paidAt->diffInMinutes(now());

        foreach (self::THRESHOLDS as $thresholdMinutes => $role) {
            if ($minutesUnacknowledged < $thresholdMinutes) {
                continue;
            }

            $alreadyEscalated = $order->events()
                ->where('meta->escalation_level', $thresholdMinutes)
                ->exists();

            if ($alreadyEscalated) {
                continue;
            }

            $branchId = $role === 'owner' ? null : $order->branch_id;

            foreach ($context->usersWithRole($role, $branchId) as $user) {
                $notifier->notify(
                    $user,
                    "Order {$order->reference} has been unacknowledged for {$thresholdMinutes}+ minutes.",
                    ['order_id' => $order->id, 'branch_id' => $order->branch_id],
                );
            }

            $order->events()->create([
                'from_status' => 'paid',
                'to_status' => 'paid',
                'actor_type' => 'system',
                'actor_id' => null,
                'meta' => ['escalation_level' => $thresholdMinutes],
            ]);
        }
    }
}
