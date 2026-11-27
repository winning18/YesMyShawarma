<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pack size of a product (R2, 2026-11-27): "Strip of 10", "Box", "Crate".
 * `units` is how many of the product's own unit it holds; `price` and
 * `cost_price` are pesewas. Its barcode is unique across every product and
 * pack (ProductCatalogService checks both tables).
 */
#[Fillable(['menu_item_id', 'name', 'units', 'price', 'cost_price', 'barcode', 'sort_order'])]
class ProductPack extends Model
{
    protected function casts(): array
    {
        return [
            'units' => 'integer',
            'price' => 'integer',
            'cost_price' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
