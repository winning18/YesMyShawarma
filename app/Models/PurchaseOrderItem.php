<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line of a purchase order (R4): `quantity` of the product or pack, `units` each, `unit_cost` pesewas each. */
#[Fillable(['purchase_order_id', 'menu_item_id', 'product_pack_id', 'quantity', 'units', 'unit_cost', 'quantity_received'])]
class PurchaseOrderItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'units' => 'integer', 'unit_cost' => 'integer', 'quantity_received' => 'integer'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(ProductPack::class, 'product_pack_id');
    }

    public function outstanding(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }
}
