<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a return (R3, 2026-11-27): how many of a sale's line came
 * back and the money given back for them (pesewas).
 */
#[Fillable(['refund_id', 'order_item_id', 'quantity', 'amount'])]
class RefundItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'integer'];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
