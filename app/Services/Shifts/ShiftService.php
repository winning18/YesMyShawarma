<?php

namespace App\Services\Shifts;

use App\Exceptions\ShiftException;
use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;

class ShiftService
{
    /**
     * A person is in one place at a time — this is checked across all
     * branches, not just the one they're clocking into. $startingCash is
     * optional for every role — ShiftController is where "staff must be
     * forced through this popup" actually lives, not here.
     *
     * $startingCash is float-for-change, not revenue (schema.md's Shifts
     * section) — it records "x was available at the start," nothing more.
     * Never add, subtract, or otherwise combine it with total_sales
     * anywhere — reports, reconciliation, everywhere. They're independent
     * facts about the same shift, not two halves of one number.
     */
    public function start(User $user, Branch $branch, ?int $startingCash = null, ?string $openingNote = null): Shift
    {
        if ($this->activeFor($user)) {
            throw ShiftException::alreadyOnShift();
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
     * $totalSales stays optional at this layer — ShiftController is where
     * "required for staff, optional for everyone else" (and "must be at
     * least $systemSales") is enforced. $systemSales is a snapshot of what
     * the system recorded at the moment of closing, not a live-derivable
     * value — see the migration that added the column. Null whenever
     * $totalSales is (never captured for non-staff, who aren't validated
     * against it at all).
     */
    public function end(Shift $shift, ?int $totalSales = null, ?int $systemSales = null, ?string $closingNote = null): Shift
    {
        $shift->update([
            'ended_at' => now(),
            'total_sales' => $totalSales,
            'system_sales' => $systemSales,
            'closing_note' => $closingNote,
        ]);

        return $shift->fresh();
    }

    public function activeFor(User $user): ?Shift
    {
        return $user->shifts()->whereNull('ended_at')->latest('started_at')->first();
    }
}
