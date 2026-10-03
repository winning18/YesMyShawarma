<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['option_group_id', 'name', 'price_delta', 'is_active'])]
class Option extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class);
    }

    /**
     * What choosing this option consumes from the *current* branch's own
     * stock, on top of whatever the item's own recipeItems() already
     * consumes — see MenuItemRecipeItem's docblock.
     */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(MenuItemRecipeItem::class, 'source_option_id')
            ->where('source_type', MenuItemRecipeItem::SOURCE_OPTION);
    }
}
