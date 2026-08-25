<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Deliberately no BranchScope here, unlike Order. ShiftService::
// activeFor() relies on querying a user's shifts across every branch ("a
// person is in one place at a time" — checked branch-wide, not just the one
// they're clocking into right now). Scoping this model would silently
// break that cross-branch check the first time someone tried to clock in
// at a second branch while still active at another.
#[Fillable(['user_id', 'branch_id', 'started_at', 'ended_at', 'starting_cash', 'total_sales', 'system_sales', 'opening_note', 'closing_note'])]
class Shift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderEvents(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }
}
