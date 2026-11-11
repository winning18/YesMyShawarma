<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which order's cash fee went into which collection — an order belongs to
 * one live collection at most (unique order_id); voiding deletes these.
 */
#[Fillable(['delivery_fee_collection_id', 'order_id', 'cash_fee'])]
class DeliveryFeeCollectionOrder extends Model
{
    public $timestamps = false;

    public function collection(): BelongsTo
    {
        return $this->belongsTo(DeliveryFeeCollection::class, 'delivery_fee_collection_id')->withoutGlobalScopes();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
