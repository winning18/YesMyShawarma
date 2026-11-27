<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'menu_item_id', 'name_snapshot', 'unit_price_snapshot',
    'quantity', 'line_total', 'is_free', 'notes',
    // Pharmacies and shops (R3): the pack sold, its units, a price changed at the till.
    'product_pack_id', 'units', 'list_price_snapshot', 'price_changed_by',
    // R4: the cost of one of this line when sold (pesewas), for profit.
    'cost_snapshot',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(ProductPack::class, 'product_pack_id');
    }

    public function priceChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_changed_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
