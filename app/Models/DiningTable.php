<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A table at a branch (Tables module), made by staff as needed. */
#[Fillable(['branch_id', 'name', 'is_active'])]
class DiningTable extends Model
{
    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tabs(): HasMany
    {
        return $this->hasMany(TableTab::class);
    }

    /** The bill open on this table now, if any. */
    public function openTab(): HasOne
    {
        return $this->hasOne(TableTab::class)->whereNull('settled_at')->latestOfMany('opened_at');
    }
}
