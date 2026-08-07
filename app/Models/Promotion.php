<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'type', 'value', 'min_order_total', 'starts_at', 'ends_at',
    'max_redemptions', 'max_per_customer', 'is_active',
])]
class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * No rows = applies to every branch — see PromotionService's
     * eligibility check, which is where that's actually enforced.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'promotion_branch');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
