<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A counter at a branch ("Counter 1") — a till of its own once a device
 * is set up as it (CounterService). Express POS step 7a.
 */
#[Fillable(['branch_id', 'name', 'device_token_hash', 'paired_at', 'is_active'])]
#[Hidden(['device_token_hash'])]
class Terminal extends Model
{
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'paired_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function isPaired(): bool
    {
        return $this->device_token_hash !== null;
    }
}
