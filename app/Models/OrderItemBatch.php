<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How many units of a sale line came from which batch (R5). */
#[Fillable(['order_item_id', 'stock_batch_id', 'units'])]
class OrderItemBatch extends Model
{
    protected function casts(): array
    {
        return ['units' => 'decimal:2'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }
}
