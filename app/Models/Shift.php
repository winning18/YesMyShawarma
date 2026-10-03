<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Deliberately no BranchScope here, unlike Order — ShiftService::
// activeForBranch() takes an explicit branch id rather than relying on
// BranchContext's currently-resolved branch, since not every call site is
// guaranteed to already be scoped to the right one (e.g. ending a shift on
// behalf of a different branch than whichever happens to be current).
//
// One open shift per branch, not per person: user_id is who *opened* it,
// ended_by_user_id who *closed* it — staff working the same branch while
// it's open join that single shift rather than each getting their own row
// (orders.md's "Shifts" section). starting_cash/total_sales/system_sales
// are therefore branch-wide figures for that session, not any one person's.
#[Fillable(['user_id', 'branch_id', 'started_at', 'ended_at', 'ended_by_user_id', 'starting_cash', 'total_sales', 'system_sales', 'opening_note', 'closing_note', 'no_expenses'])]
class Shift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'no_expenses' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderEvents(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ShiftExpense::class);
    }
}
