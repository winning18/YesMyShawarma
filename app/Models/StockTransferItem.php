<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product in a transfer (R4): units sent, and units the other branch says arrived. */
#[Fillable(['stock_transfer_id', 'menu_item_id', 'units', 'received_units'])]
class StockTransferItem extends Model
{
    protected function casts(): array
    {
        return ['units' => 'integer', 'received_units' => 'integer'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }
}
