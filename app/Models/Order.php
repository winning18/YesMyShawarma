<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'track_token', 'customer_id', 'branch_id',
    'fulfilment_type',
    'subtotal', 'discount_total', 'delivery_fee', 'total',
    'payment_method', 'payment_status', 'delivery_address_snapshot',
    'scheduled_for',
])]
#[ScopedBy([BranchScope::class])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'delivery_address_snapshot' => 'array',
            'claimed_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'placed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
