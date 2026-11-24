<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Content\HomeStripService;
use App\Services\Content\SitePageService;
use App\Services\Content\ThemeHomeService;
use App\Services\Content\WebsiteThemeService;
use App\Services\Customers\CustomerBranchSelection;
use App\Services\Menu\CustomerMenuService;
use App\Services\Menu\PopularItemsService;
use App\Services\Modules\ModuleService;
use App\Services\Promotions\PromotionService;
use App\Services\Seo\RestaurantSchemaBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(CustomerBranchSelection $selection, RestaurantSchemaBuilder $restaurantSchema, PromotionService $promotions, PopularItemsService $popular, SitePageService $pages, WebsiteThemeService $themes): View|RedirectResponse
    {
        // POS-only (Express POS step 4): with no Website module, the
        // business's address opens the staff login.
        if (! app(ModuleService::class)->enabled(ModuleService::WEBSITE)) {
            return redirect()->route('login');
        }

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

        // Shown ahead of the normal hero slider on a day an automatic
        // promotion is active (recurring_days) — see PromotionService::
        // findActiveAutomatic()'s own note on why a branch-restricted
        // promotion only shows once a branch is actually selected.
        $promotion = $promotions->findActiveAutomatic($selection->current());

        $schema = $restaurantSchema->forActiveBranches();
        $popularItems = $this->popularItems($popular, $selection->current());

        return view($themes->view('home'), [
            'meta' => $pages->meta('home', [
                ':branches' => collect($schema)->map(fn (array $branch) => Str::after($branch['name'], ': '))->join(', ', ' and '),
            ]),
            'heroSlides' => $heroSlides,
            'promotionBanner' => $promotion ? [
                'headline' => $promotion->banner_headline,
                'imageUrl' => $promotion->bannerImageUrl(),
            ] : null,
            'popularItems' => $popularItems,
            'menuSliders' => $themes->current() === WebsiteThemeService::CLASSIC ? $this->menuSliders($selection->current()) : collect(),
            'restaurantSchema' => $schema,
            ...$this->themeData($themes->current(), $heroSlides, $popularItems, $selection, $popular),
        ]);
    }

    /**
     * What the Photo-led and One-page themes add to the home page
     * (WebsiteThemeService). Classic needs nothing more.
     */
    private function themeData(string $theme, Collection $heroSlides, Collection $popularItems, CustomerBranchSelection $selection, PopularItemsService $popular): array
    {
        if ($theme === WebsiteThemeService::CLASSIC) {
            return [];
        }

        $home = app(ThemeHomeService::class);
        $menu = app(CustomerMenuService::class);
        $branch = $theme === WebsiteThemeService::ONE_PAGE ? $menu->branchFor($selection) : $selection->current();
        $branch ??= Branch::where('is_active', true)->orderBy('id')->first();

        $data = [
            'hero' => $home->hero($heroSlides),
            'quickFacts' => $home->quickFacts($branch),
            'reviews' => $home->reviews(),
        ];

        if ($theme === WebsiteThemeService::PHOTO) {
            return $data + [
                'featured' => $home->featured($popularItems),
                'categoryTiles' => $home->categoryTiles(),
                'gallery' => $home->gallery(),
            ];
        }

        return $data + [
            'branch' => $branch,
            'categories' => $branch ? $menu->categories($branch) : collect(),
            'popularIds' => $popular->badged(),
            'branches' => $home->branches(),
            'selectedBranchId' => $selection->id(),
            'about' => $home->about(),
            'contact' => $home->contact(),
        ];
    }

    /**
     * Home page menu-item strips, in display order. A business can set its
     * own grouping in the home_menu_sliders setting (Yes My Shawarma's
     * merges hot dogs and loaded fries into one strip); otherwise there's
     * one strip per active category, in menu order.
     */
    private function menuSliders(?Branch $branch): Collection
    {
        return collect(app(HomeStripService::class)->strips())
            ->map(fn (array $slider) => [
                'title' => $slider['title'],
                // First slug only — enough for the "Show all" link's
                // ?category= filter on the menu page. A slider spanning
                // several categories (e.g. Hot Dogs & Loaded Fries) just
                // lands on the first of the group, which sits right at the
                // start of that section since they're ordered adjacently.
                'categorySlug' => $slider['slugs'][0],
                'items' => $this->itemsForSlugs($slider['slugs'], $branch),
            ])
            ->filter(fn (array $slider) => $slider['items']->isNotEmpty())
            ->values();
    }

    /**
     * Items stay on the marquee even when sold out — see
     * home.blade.php's gray-out treatment — only $item->is_active (a
     * permanent removal, not a stock state) drops an item entirely.
     * Availability is branch_menu_item's pivot (schema.md), so it can
     * only be known once a branch is selected (MenuController's own
     * resolveBranch pattern); with none selected yet every active item is
     * treated as available, matching menu.show's own redirect-to-branch
     * behaviour for that case.
     */
    private function itemsForSlugs(array $slugs, ?Branch $branch): Collection
    {
        $categoryIds = Category::whereIn('slug', $slugs)->pluck('id');

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $items = MenuItem::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->withAvailability($items, $branch);
    }

    /**
     * The "Most ordered this month" strip, most ordered first, each with
     * its rank. Sold-out items stay in with the same gray-out as the
     * category strips.
     */
    private function popularItems(PopularItemsService $popular, ?Branch $branch): Collection
    {
        $ranks = $popular->ranks();

        if ($ranks === []) {
            return collect();
        }

        $items = MenuItem::whereIn('id', array_keys($ranks))->where('is_active', true)->get()
            ->sortBy(fn (MenuItem $item) => $ranks[$item->id])
            ->values()
            ->map(function (MenuItem $item) use ($ranks) {
                $item->popularRank = $ranks[$item->id];

                return $item;
            });

        return $this->withAvailability($items, $branch);
    }

    private function withAvailability(Collection $items, ?Branch $branch): Collection
    {
        $availableIds = $branch
            ? $branch->menuItems()
                ->whereIn('menu_items.id', $items->pluck('id'))
                ->wherePivot('is_available', true)
                ->pluck('menu_items.id')
            : $items->pluck('id');

        // map(), not each() — each() stops iterating the instant a
        // callback returns exactly false, and $item->isAvailable = false
        // (the sold-out case) IS exactly false, so a single sold-out item
        // used to silently cut the loop short and leave every item after
        // it with isAvailable unset — read as falsy by Blade, graying out
        // the rest of the category too.
        return $items->map(function (MenuItem $item) use ($availableIds) {
            $item->isAvailable = $availableIds->contains($item->id);

            return $item;
        });
    }
}
