<?php

namespace App\Console\Commands;

use App\Contracts\Notifier;
use App\Models\Order;
use App\Services\Branches\BranchContext;
use App\Services\Branches\WorkingHoursService;
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
     * @var array<int, list<string>>
     */
    private const THRESHOLDS = [
        // general_manager holds everything a manager holds (permissions.md)
        // at every branch they oversee, including this notification — a
        // branch with no general_manager just gets its plain manager(s).
        5 => ['manager', 'general_manager'],
        10 => ['owner'],
    ];

    public function handle(BranchContext $context, Notifier $notifier, WorkingHoursService $workingHours): int
    {
        $paidOrders = Order::where('status', 'paid')->with(['events', 'branch'])->get();

        foreach ($paidOrders as $order) {
            $this->escalateIfDue($order, $context, $notifier, $workingHours);
        }

        return self::SUCCESS;
    }

    /**
     * No SMS while the branch is outside its working hours — an order
     * sitting unacknowledged overnight isn't a problem, it's the expected
     * state until staff are back (see WorkingHoursService, CheckoutController
     * for the customer-facing side of this). Deliberately not marked as
     * "escalated" when skipped this way — the moment the branch is open
     * again, it's evaluated fresh, and escalates immediately if it's
     * already past a threshold rather than waiting out a new countdown.
     */
    private function escalateIfDue(Order $order, BranchContext $context, Notifier $notifier, WorkingHoursService $workingHours): void
    {
        if (! $workingHours->isOpenNow($order->branch)) {
            return;
        }

        $paidAt = $order->events
            ->where('to_status', 'paid')
            ->sortByDesc('created_at')
            ->first()
            ?->created_at ?? $order->placed_at;

        if (! $paidAt) {
            return;
        }

        $minutesUnacknowledged = $paidAt->diffInMinutes(now());

        foreach (self::THRESHOLDS as $thresholdMinutes => $roles) {
            if ($minutesUnacknowledged < $thresholdMinutes) {
                continue;
            }

            $alreadyEscalated = $order->events()
                ->where('meta->escalation_level', $thresholdMinutes)
                ->exists();

            if ($alreadyEscalated) {
                continue;
            }

            $recipients = collect($roles)
                ->flatMap(function (string $role) use ($context, $order) {
                    $branchId = $role === 'owner' ? null : $order->branch_id;

                    return $context->usersWithRole($role, $branchId);
                })
                ->unique('id');

            foreach ($recipients as $user) {
                $notifier->notify(
                    $user->phone,
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
