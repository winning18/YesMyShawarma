<?php

namespace App\Services\Content;

use App\Models\Category;
use App\Services\Settings\SettingsService;

/**
 * The home page's rows of menu items ("strips"): each has a title and the
 * categories it shows, in order. Stored as the home_menu_sliders setting
 * (JSON); with none set, there's one strip per active category in menu
 * order. Edited under Settings → Website content → Home page strips.
 */
class HomeStripService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * What the home page shows: the business's own strips, or one per
     * category.
     *
     * @return list<array{title: string, slugs: list<string>}>
     */
    public function strips(): array
    {
        return $this->configured() ?? $this->perCategory();
    }

    /**
     * @return list<array{title: string, slugs: list<string>}>|null
     */
    public function configured(): ?array
    {
        $stored = json_decode((string) $this->settings->get(SettingsService::HOME_MENU_SLIDERS), true);

        return is_array($stored) && $stored !== [] ? $stored : null;
    }

    /**
     * @return list<array{title: string, slugs: list<string>}>
     */
    public function perCategory(): array
    {
        return Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Category $category) => ['title' => $category->name, 'slugs' => [$category->slug]])
            ->all();
    }

    /**
     * @param  list<array{title: string, slugs: list<string>}>  $strips
     */
    public function save(array $strips): void
    {
        $clean = array_values(array_map(fn (array $strip) => [
            'title' => trim($strip['title']),
            'slugs' => array_values(array_unique($strip['slugs'])),
        ], $strips));

        $this->settings->set(SettingsService::HOME_MENU_SLIDERS, $clean === [] ? null : json_encode($clean));
    }

    /** Back to one strip per category. */
    public function reset(): void
    {
        $this->settings->set(SettingsService::HOME_MENU_SLIDERS, null);
    }
}
