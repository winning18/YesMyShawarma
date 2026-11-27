<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A stock count at a branch (R4, 2026-11-27; StockCountService). */
#[Fillable(['branch_id', 'status', 'started_by', 'finished_by', 'finished_at', 'note'])]
#[ScopedBy([BranchScope::class])]
class StockCount extends Model
{
    public const OPEN = 'open';

    public const FINISHED = 'finished';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }
}
