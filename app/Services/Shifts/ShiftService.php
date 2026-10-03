<?php

namespace App\Services\Shifts;

use App\Exceptions\ShiftException;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;

class ShiftService
{
    /**
     * One open shift per branch, not per person — if $branch already has
     * one running (started by this user earlier, or by someone else
     * entirely), $user simply joins it by working there; this returns the
     * existing row unchanged rather than erroring or creating a second,
     * overlapping one. A second staff member logging in mid-shift needs no
     * action at all beyond this — ResolveCurrentBranch/OrderDashboardController's
     * own forceShiftStart checks already skip the start-shift prompt entirely
     * once activeForBranch() finds one open.
     *
     * $startingCash is optional for every role — ShiftController is where
     * "staff must be forced through this popup" actually lives, not here.
     * It's float-for-change, not revenue (schema.md's Shifts section) — it
     * records "x was available at the start," nothing more. Never add,
     * subtract, or otherwise combine it with total_sales anywhere —
     * reports, reconciliation, everywhere. They're independent facts about
     * the same shift, not two halves of one number. Only meaningful the
     * first time a branch's shift is opened for the day — a second staff
     * member joining an already-open one has nothing of their own to set it
     * to, so $startingCash/$openingNote are silently ignored on a join.
     */
    public function start(User $user, Branch $branch, ?int $startingCash = null, ?string $openingNote = null): Shift
    {
        if ($existing = $this->activeForBranch($branch->id)) {
            return $existing;
        }

        return Shift::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'started_at' => now(),
            'starting_cash' => $startingCash,
            'opening_note' => $openingNote,
        ]);
    }

    /**
     * $endedBy may be a different person than whoever opened it (user_id)
     * — any staff member working the branch can close its shared shift,
     * same permission as starting one.
     *
     * $totalSales/$systemSales stay optional at this layer — ShiftController
     * is where "required, and must be at least $systemSales" is enforced.
     * $systemSales is a snapshot of what the system recorded at the moment
     * of closing, not a live-derivable value — see the migration that added
     * the column.
     *
     * $expenses is a list of ['description' => string, 'amount' => int
     * pesewas] rows, already converted and validated by the controller —
     * this just persists them as shift_expenses, an immutable ledger same
     * as order_events/stock_movements. $noExpenses records an explicit "I
     * confirm there were none" so the Today report can tell that apart
     * from a shift that simply predates this feature (schema.md).
     */
    public function end(Shift $shift, User $endedBy, ?int $totalSales = null, ?int $systemSales = null, ?string $closingNote = null, array $expenses = [], bool $noExpenses = false): Shift
    {
        $shift->update([
            'ended_at' => now(),
            'ended_by_user_id' => $endedBy->id,
            'total_sales' => $totalSales,
            'system_sales' => $systemSales,
            'closing_note' => $closingNote,
            'no_expenses' => $noExpenses,
        ]);

        foreach ($expenses as $expense) {
            $shift->expenses()->create([
                'description' => $expense['description'],
                'amount' => $expense['amount'],
            ]);
        }

        return $shift->fresh();
    }

    /**
     * The one open shift at $branchId, if any — the sole "is this branch
     * mid-shift" check now that shifts are branch-scoped, not per-user.
     * Every caller that used to ask "is $user on shift" asks this instead,
     * resolving the branch from BranchContext/the order/etc. first.
     */
    public function activeForBranch(int $branchId): ?Shift
    {
        return Shift::where('branch_id', $branchId)->whereNull('ended_at')->latest('started_at')->first();
    }
}
