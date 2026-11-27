<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Who a pharmacy or shop buys from (R4, 2026-11-27); shared by every branch. */
#[Fillable(['name', 'phone', 'email', 'address', 'notes'])]
class Supplier extends Model
{
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
