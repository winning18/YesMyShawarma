<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock moved between two branches (R4, 2026-11-27; StockTransferService).
 * It belongs to both, so no BranchScope: StockTransferService filters by
 * either side.
 */
#[Fillable(['from_branch_id', 'to_branch_id', 'status', 'sent_by', 'received_by', 'sent_at', 'received_at', 'note'])]
class StockTransfer extends Model
{
    public const SENT = 'sent';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
