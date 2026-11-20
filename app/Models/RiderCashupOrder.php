<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which order's cash fee went into which collection — an order belongs to
 * one live collection at most (unique order_id); voiding deletes these.
 */
/** One delivery in a rider cash-up: the food money and fee it covered, and any fee the business owed the rider. */
#[Fillable(['rider_cashup_id', 'order_id', 'food_amount', 'fee_amount', 'fee_credit'])]
class RiderCashupOrder extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['food_amount' => 'integer', 'fee_amount' => 'integer', 'fee_credit' => 'integer'];
    }

    public function cashup(): BelongsTo
    {
        return $this->belongsTo(RiderCashup::class, 'rider_cashup_id')->withoutGlobalScopes();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
