<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Home page menu-item marquees, in display order. Direction alternates
     * per row on purpose (a visual zig-zag), and hot-dogs/loaded-fries are
     * deliberately merged into one row rather than two.
     *
     * @var list<array{title: string, slugs: list<string>, direction: string}>
     */
    private const MENU_SLIDERS = [
        ['title' => 'Shawarma favourites', 'slugs' => ['shawarma'], 'direction' => 'right'],
        ['title' => 'Burgers', 'slugs' => ['burgers'], 'direction' => 'left'],
        ['title' => 'Hot Dogs & Loaded Fries', 'slugs' => ['hot-dogs', 'loaded-fries'], 'direction' => 'right'],
        ['title' => 'Sandwiches', 'slugs' => ['sandwiches'], 'direction' => 'left'],
        ['title' => 'Drinks', 'slugs' => ['drinks'], 'direction' => 'right'],
    ];

    public function index(): View
    {
        // Every category is hero-slide-eligible (Hero Slider dashboard
        // page), but only ones staff actually gave a photo appear on the
        // live carousel — otherwise a freshly created category would
        // immediately show an empty slide before anyone got to it.
        $heroSlides = Category::where('is_active', true)
            ->whereNotNull('hero_image_path')
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'tagline' => $category->tagline ?? '',
                'imageUrl' => $category->heroImageUrl(),
            ])
            ->values();

        return view('home', [
            'heroSlides' => $heroSlides,
            'menuSliders' => $this->menuSliders(),
        ]);
    }

    private function menuSliders(): Collection
    {
        return collect(self::MENU_SLIDERS)
            ->map(fn (array $slider) => [
                'title' => $slider['title'],
                'direction' => $slider['direction'],
                'items' => $this->itemsForSlugs($slider['slugs']),
            ])
            ->filter(fn (array $slider) => $slider['items']->isNotEmpty())
            ->values();
    }

    private function itemsForSlugs(array $slugs): Collection
    {
        $categoryIds = Category::whereIn('slug', $slugs)->pluck('id');

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return MenuItem::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
