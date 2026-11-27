<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'category_id', 'name', 'slug', 'description', 'base_price',
    'image_path', 'is_active', 'sort_order',
    // Products (pharmacies and shops, R2): ProductCatalogService.
    'sku', 'barcode', 'unit_name', 'cost_price',
    // Medicines (pharmacies, R5).
    'generic_name', 'strength', 'dosage_form', 'fda_number', 'medicine_class',
])]
class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * A medicine's class (R5, owner 2026-11-27; names to confirm with the
     * pilot pharmacist): OTC sells freely; the rest need a manager's
     * approval at the till, and Prescription-only and Controlled a
     * prescription too. Null: not a medicine.
     */
    public const OTC = 'otc';

    public const PHARMACY_MEDICINE = 'pharmacy';

    public const PRESCRIPTION_ONLY = 'pom';

    public const CONTROLLED = 'controlled';

    public const MEDICINE_CLASSES = [
        self::OTC => 'Over the counter (OTC)',
        self::PHARMACY_MEDICINE => 'Pharmacy medicine',
        self::PRESCRIPTION_ONLY => 'Prescription only (POM)',
        self::CONTROLLED => 'Controlled drug',
    ];

    public function isMedicine(): bool
    {
        return $this->medicine_class !== null;
    }

    public function needsApproval(): bool
    {
        return in_array($this->medicine_class, [self::PHARMACY_MEDICINE, self::PRESCRIPTION_ONLY, self::CONTROLLED], true);
    }

    public function needsPrescription(): bool
    {
        return in_array($this->medicine_class, [self::PRESCRIPTION_ONLY, self::CONTROLLED], true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Pack sizes (R2): a strip, box or crate of this product, each with its own price and barcode. */
    public function packs(): HasMany
    {
        return $this->hasMany(ProductPack::class)->orderBy('sort_order')->orderBy('units');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_menu_item')
            ->withPivot(['is_available', 'unavailable_until'])
            ->withTimestamps();
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'menu_item_option_group')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MenuItemSchedule::class);
    }

    /**
     * How this item decomposes on the Today report when it's a combo —
     * empty for a plain item, which is instead recorded under its own
     * name. See MenuItemComponent's docblock.
     */
    public function components(): HasMany
    {
        return $this->hasMany(MenuItemComponent::class);
    }

    /**
     * What one unit of this item consumes from the *current* branch's own
     * stock (BranchScope on MenuItemRecipeItem) — empty for an item with
     * no recipe configured at this branch, which simply never touches
     * stock when ordered. See MenuItemRecipeItem's docblock.
     */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(MenuItemRecipeItem::class, 'source_menu_item_id')
            ->where('source_type', MenuItemRecipeItem::SOURCE_MENU_ITEM);
    }

    /** See Branch::imageUrl()'s docblock — same cache-busting, same reason. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path).'?v='.$this->updated_at->timestamp : null;
    }
}
