<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A batch of a medicine at a branch (R5, 2026-11-27; BatchService): its
 * number, expiry and how much of it is left, in the product's own unit.
 * A null expiry is stock from before batches were kept, or a count's extra.
 */
#[Fillable(['stock_item_id', 'batch_number', 'expiry_date', 'quantity', 'received_quantity', 'purchase_order_item_id'])]
class StockBatch extends Model
{
    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'quantity' => 'decimal:2', 'received_quantity' => 'decimal:2'];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class)->withoutGlobalScopes();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(now('Africa/Accra')->startOfDay());
    }
}
