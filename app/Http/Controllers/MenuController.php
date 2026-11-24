<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Content\SitePageService;
use App\Services\Content\WebsiteThemeService;
use App\Services\Customers\CustomerBranchSelection;
use App\Services\Menu\CustomerMenuService;
use App\Services\Menu\PopularItemsService;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(CustomerBranchSelection $selection, PopularItemsService $popular, SitePageService $pages, CustomerMenuService $menu, WebsiteThemeService $themes): View|RedirectResponse
    {
        $branch = $this->resolveBranch($selection);

        if ($branch instanceof RedirectResponse) {
            return $branch;
        }

        $categories = $menu->categories($branch);

        return view($themes->view('menu.index'), [
            'meta' => $pages->meta('menu'),
            'branch' => $branch,
            'categories' => $categories,
            'popularIds' => $popular->badged(),
            // Drinks sit side by side with a small square photo, everything else stacked.
            'sideBySideCategories' => Category::where('is_beverage', true)->pluck('slug')->all(),
            'menuSchema' => $this->menuSchema($branch, $categories),
        ]);
    }

    public function show(MenuItem $menuItem, CustomerBranchSelection $selection, PopularItemsService $popular, SitePageService $pages, WebsiteThemeService $themes): View|RedirectResponse
    {
        $branch = $this->resolveBranch($selection);

        if ($branch instanceof RedirectResponse) {
            return $branch;
        }

        // A sold-out item (is_available off at this branch) still gets its
        // page — shown as sold out with no add-to-cart — rather than a 404:
        // the sitemap lists it, and a temporarily out-of-stock item
        // flickering in and out of Google's index helps nobody. Ordering it
        // is still refused server-side (MenuPricingService).
        $item = $branch->menuItems()
            ->where('menu_items.id', $menuItem->id)
            ->where('menu_items.is_active', true)
            ->with(['optionGroups.options' => fn ($query) => $query->where('is_active', true)])
            ->first();

        if (! $item) {
            abort(404);
        }

        $isAvailable = (bool) $item->pivot->is_available;

        // Every category marked as drinks (categories.is_beverage).
        $drinkCategoryIds = Category::where('is_beverage', true)->where('is_active', true)->pluck('id');

        $drinks = $drinkCategoryIds->isNotEmpty()
            ? $branch->menuItems()
                ->whereIn('menu_items.category_id', $drinkCategoryIds)
                ->where('menu_items.id', '!=', $item->id)
                ->where('menu_items.is_active', true)
                ->wherePivot('is_available', true)
                ->orderBy('menu_items.sort_order')
                ->get()
            : collect();

        return view($themes->view('menu.show'), [
            'metaTitle' => $pages->meta('menu-item', [':item' => $item->name])['title'],
            'branch' => $branch,
            'item' => $item,
            'drinks' => $drinks,
            'isAvailable' => $isAvailable,
            'popularRank' => $popular->rank($item->id),
            'metaDescription' => $this->metaDescription($item),
            'productSchema' => $this->productSchema($item, $isAvailable),
        ]);
    }

    /**
     * The item's search snippet: its own description, then as much of the
     * price/delivery line as fits in 160 characters — whole phrases only,
     * never cut mid-word. Without a description it falls back to "Order X
     * from Yes My Shawarma."
     */
    private function metaDescription(MenuItem $item): string
    {
        $base = $item->description
            ? rtrim($item->description, '. ').'.'
            : __('Order :item from :name.', ['item' => $item->name, 'name' => config('app.name')]);
        $price = number_format($item->base_price / 100, 2);

        foreach ([
            $this->city()
                ? __(' GH₵:price, :how in :city.', ['price' => $price, 'how' => $this->how(), 'city' => $this->city()])
                : __(' GH₵:price, :how.', ['price' => $price, 'how' => $this->how()]),
            __(' GH₵:price.', ['price' => $price]),
            '',
        ] as $suffix) {
            if (mb_strlen($base.$suffix) <= 160) {
                return $base.$suffix;
            }
        }

        return Str::limit($base, 157);
    }

    /**
     * Product structured data (schema.org) for this item's own page —
     * lets Google show price/availability directly in search results.
     * $item is already scoped to wherePivot('is_available', true) by the
     * time this runs (a 404 happens otherwise), so availability here is
     * always InStock — there's no other state this method ever sees.
     */
    private function productSchema(MenuItem $item, bool $isAvailable = true): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $item->name,
            // Google's merchant-listing check warns without a description
            // or brand — no item has a description yet, so fall back to
            // the same sentence the page's meta description uses.
            'description' => $item->description
                ?: ($this->city()
                    ? __('Order :item from :name, :how in :city.', ['item' => $item->name, 'name' => config('app.name'), 'how' => $this->how(), 'city' => $this->city()])
                    : __('Order :item from :name, :how.', ['item' => $item->name, 'name' => config('app.name'), 'how' => $this->how()])),
            'brand' => [
                '@type' => 'Brand',
                'name' => config('app.name'),
            ],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'GHS',
                'price' => number_format($item->base_price / 100, 2, '.', ''),
                'availability' => $isAvailable ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ];

        if ($item->imageUrl()) {
            $schema['image'] = $item->imageUrl();
        }

        return $schema;
    }

    /**
     * Menu structured data (schema.org) for the whole page — lets an
     * answer engine read every item and price in one request rather than
     * needing to crawl each item's own Product page individually. Built
     * from the exact same $categories the page itself renders (index()
     * above), so the two can never drift.
     *
     * @param  Collection<int, array{category: Category, items: Collection<int, MenuItem>}>  $categories
     */
    private function menuSchema(Branch $branch, Collection $categories): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Menu',
            'name' => config('app.name').': '.$branch->name.' Menu',
            'hasMenuSection' => $categories->map(fn (array $group) => [
                '@type' => 'MenuSection',
                'name' => $group['category']->name,
                'hasMenuItem' => $group['items']->map(fn (MenuItem $item) => $this->menuItemSchema($item))->values()->all(),
            ])->values()->all(),
        ];
    }

    private function menuItemSchema(MenuItem $item): array
    {
        $schema = [
            '@type' => 'MenuItem',
            'name' => $item->name,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'GHS',
                'price' => number_format($item->base_price / 100, 2, '.', ''),
            ],
        ];

        if ($item->description) {
            $schema['description'] = $item->description;
        }

        return $schema;
    }

    /**
     * Shared by index() and show(). A visitor with no branch selected yet
     * — including every search-engine crawler, which never carries a
     * session — used to be redirected to /branches with no menu content
     * at all, making /menu and every /menu/{item} page unindexable. Now
     * defaults to the first active branch instead (session only, flagged
     * SOURCE_DEFAULT so the branch bar tells the customer it was picked
     * for them),
     * exactly like HomeController's marquee already treats "no branch
     * selected" as "show it anyway" rather than hiding content. Real
     * visitors still see the branch switcher and can pick their actual
     * branch at any time; this only changes what's shown before they do.
     */
    private function resolveBranch(CustomerBranchSelection $selection): Branch|RedirectResponse
    {
        $branch = $selection->current();

        // A branch selected earlier in this session may have been
        // deactivated since — CustomerBranchSelection has no opinion on
        // that (it's a plain "what's selected" accessor), so it's checked
        // at the point of use instead. Still a real redirect case (not a
        // crawlability concern): only reachable once a branch was already
        // explicitly selected, not on a fresh, session-less visit.
        if ($branch && ! $branch->is_active) {
            return redirect()->route('branches.index')->with('status', 'That branch is no longer available. Please choose another.');
        }

        if (! $branch) {
            $branch = Branch::where('is_active', true)->orderBy('id')->first();

            if (! $branch) {
                return redirect()->route('branches.index')->with('status', 'Choose a branch to see its menu.');
            }

            $selection->set($branch->id, CustomerBranchSelection::SOURCE_DEFAULT);
        }

        return $branch;
    }

    /** "for pickup" alone while Delivery and riders is switched off (ModuleService). */
    private function how(): string
    {
        return app(ModuleService::class)->enabled(ModuleService::DELIVERY) ? __('for delivery or pickup') : __('for pickup');
    }

    private function city(): ?string
    {
        return app(BusinessProfile::class)->city();
    }
}
