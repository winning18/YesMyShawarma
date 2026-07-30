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
     * branches, not just the one they're clocking into.
     */
    public function start(User $user, Branch $branch, ?string $openingNote = null): Shift
    {
        if ($this->activeFor($user)) {
            throw ShiftException::alreadyOnShift();
        }

        return Shift::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'started_at' => now(),
            'opening_note' => $openingNote,
        ]);
    }

    public function end(Shift $shift, ?string $closingNote = null): Shift
    {
        $shift->update([
            'ended_at' => now(),
            'closing_note' => $closingNote,
        ]);

        return $shift->fresh();
    }

    public function activeFor(User $user): ?Shift
    {
        return $user->shifts()->whereNull('ended_at')->latest('started_at')->first();
    }
}
