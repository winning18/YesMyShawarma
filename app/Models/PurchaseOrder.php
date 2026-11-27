<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock a branch orders from a supplier and receives (R4, 2026-11-27;
 * PurchaseOrderService). `total` is pesewas.
 */
#[Fillable(['branch_id', 'supplier_id', 'reference', 'status', 'ordered_at', 'received_at', 'created_by', 'received_by', 'note', 'total'])]
#[ScopedBy([BranchScope::class])]
class PurchaseOrder extends Model
{
    public const DRAFT = 'draft';

    public const ORDERED = 'ordered';

    public const PART_RECEIVED = 'part_received';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime', 'received_at' => 'datetime', 'total' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::DRAFT, self::ORDERED, self::PART_RECEIVED], true);
    }
}
