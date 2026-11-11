<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One rider handing in their cash delivery fees at one branch for a
 * period (owner, 2026-11-11). See DeliveryFeeCollectionService.
 */
#[Fillable([
    'branch_id', 'rider_id', 'period_from', 'period_to', 'expected', 'received', 'order_count',
    'note', 'collected_by', 'collected_at', 'voided_at', 'voided_by', 'void_reason',
])]
#[ScopedBy([BranchScope::class])]
class DeliveryFeeCollection extends Model
{
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'expected' => 'integer',
            'received' => 'integer',
            'collected_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Unscoped, like Order's: a GM or owner voids collections at branches
     * other than the one in their session; the controller checks the
     * collection's own branch straight after.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->resolveRouteBindingQuery($this, $value, $field)
            ->withoutGlobalScope(BranchScope::class)
            ->first();
    }

    /**
     * Received minus expected: negative is short, positive is over.
     */
    public function difference(): int
    {
        return $this->received - $this->expected;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DeliveryFeeCollectionOrder::class);
    }
}
