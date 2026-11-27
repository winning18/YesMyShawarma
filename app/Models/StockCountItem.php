<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product in a stock count (R4): what the system had when the count started, and what was counted. */
#[Fillable(['stock_count_id', 'stock_item_id', 'expected', 'counted'])]
class StockCountItem extends Model
{
    protected function casts(): array
    {
        return ['expected' => 'decimal:2', 'counted' => 'decimal:2'];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class)->withoutGlobalScopes();
    }
}
