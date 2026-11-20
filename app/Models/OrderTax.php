<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tax line on an order as it was at the sale (Express POS step 9):
 * the tax on the food and on the delivery fee, kept apart. See TaxService.
 */
#[Fillable(['order_id', 'name', 'rate_basis_points', 'inclusive', 'food_amount', 'delivery_amount'])]
class OrderTax extends Model
{
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'rate_basis_points' => 'integer',
            'inclusive' => 'boolean',
            'food_amount' => 'integer',
            'delivery_amount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
