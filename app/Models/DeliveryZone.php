<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id', 'name', 'delivery_fee', 'min_order_total',
    'radius_metres', 'centre_lat', 'centre_lng', 'is_active',
])]
#[ScopedBy([BranchScope::class])]
class DeliveryZone extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'centre_lat' => 'decimal:7',
            'centre_lng' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
