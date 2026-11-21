<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One table's bill (Tables module): rounds (orders) go to the kitchen as
 * they're ordered; the bill is settled once, by cash or MoMo, at a till.
 * See TableTabService.
 */
#[Fillable([
    'branch_id', 'dining_table_id', 'reference', 'opened_by', 'opened_at', 'customer_name', 'customer_phone', 'settled_at', 'settled_by',
    'shift_id', 'payment_method', 'payment_reference', 'total',
])]
class TableTab extends Model
{
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'dining_table_id' => 'integer',
            'shift_id' => 'integer',
            'opened_at' => 'datetime',
            'settled_at' => 'datetime',
            'total' => 'integer',
        ];
    }

    public function isOpen(): bool
    {
        return $this->settled_at === null;
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Every round, cancelled ones included (the bill leaves those out). */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->withoutGlobalScopes();
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
