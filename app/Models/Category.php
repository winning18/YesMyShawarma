<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'slug', 'sort_order', 'is_active', 'hero_image_path'])]
class Category extends Model
{
    use HasFactory;

    /**
     * Categories featured on the home page hero slider, in display order,
     * with their tagline copy. Single source of truth for both the
     * slider itself (HomeController) and the staff upload screen
     * (CategoryHeroImageController) — a slug listed in one but not the
     * other would silently drift them out of sync.
     *
     * @var array<string, string>
     */
    public const HERO_SLIDE_TAGLINES = [
        'shawarma' => 'Wrapped fresh, packed with flavour.',
        'burgers' => 'Stacked high, fired on the grill.',
        'loaded-fries' => 'Piled with toppings, made to share.',
        'hot-dogs' => 'Classic, simple, always hits the spot.',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function heroImageUrl(): ?string
    {
        return $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null;
    }
}
