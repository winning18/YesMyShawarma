<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(CustomerBranchSelection $selection): View|RedirectResponse
    {
        $branch = $this->resolveBranch($selection);

        if ($branch instanceof RedirectResponse) {
            return $branch;
        }

        // Ordered by categories.sort_order explicitly, then by each item's
        // own sort_order within that category — grouping a flat item query
        // by category name after the fact doesn't actually respect category
        // order (item sort_order is only unique within its own category, so
        // items from different categories interleave arbitrarily).
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'category' => $category,
                'items' => $branch->menuItems()
                    ->where('menu_items.category_id', $category->id)
                    ->where('menu_items.is_active', true)
                    ->wherePivot('is_available', true)
                    ->with(['optionGroups.options' => fn ($query) => $query->where('is_active', true)])
                    ->orderBy('menu_items.sort_order')
                    ->get(),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();

        return view('menu.index', [
            'branch' => $branch,
            'categories' => $categories,
        ]);
    }

    public function show(MenuItem $menuItem, CustomerBranchSelection $selection): View|RedirectResponse
    {
        $branch = $this->resolveBranch($selection);

        if ($branch instanceof RedirectResponse) {
            return $branch;
        }

        $item = $branch->menuItems()
            ->where('menu_items.id', $menuItem->id)
            ->where('menu_items.is_active', true)
            ->wherePivot('is_available', true)
            ->with(['optionGroups.options' => fn ($query) => $query->where('is_active', true)])
            ->first();

        if (! $item) {
            abort(404);
        }

        $drinksCategory = Category::where('slug', 'drinks')->first();

        $drinks = $drinksCategory
            ? $branch->menuItems()
                ->where('menu_items.category_id', $drinksCategory->id)
                ->where('menu_items.id', '!=', $item->id)
                ->where('menu_items.is_active', true)
                ->wherePivot('is_available', true)
                ->orderBy('menu_items.sort_order')
                ->get()
            : collect();

        return view('menu.show', [
            'branch' => $branch,
            'item' => $item,
            'drinks' => $drinks,
            'productSchema' => $this->productSchema($item),
        ]);
    }

    /**
     * Product structured data (schema.org) for this item's own page —
     * lets Google show price/availability directly in search results.
     * $item is already scoped to wherePivot('is_available', true) by the
     * time this runs (a 404 happens otherwise), so availability here is
     * always InStock — there's no other state this method ever sees.
     */
    private function productSchema(MenuItem $item): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $item->name,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'GHS',
                'price' => number_format($item->base_price / 100, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
            ],
        ];

        if ($item->description) {
            $schema['description'] = $item->description;
        }

        if ($item->imageUrl()) {
            $schema['image'] = $item->imageUrl();
        }

        return $schema;
    }

    /**
     * Shared by index() and show(). A visitor with no branch selected yet
     * — including every search-engine crawler, which never carries a
     * session — used to be redirected to /branches with no menu content
     * at all, making /menu and every /menu/{item} page unindexable. Now
     * silently defaults to (and persists) the first active branch instead,
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
            return redirect()->route('branches.index')->with('status', 'That branch is no longer available — please choose another.');
        }

        if (! $branch) {
            $branch = Branch::where('is_active', true)->orderBy('id')->first();

            if (! $branch) {
                return redirect()->route('branches.index')->with('status', 'Choose a branch to see its menu.');
            }

            $selection->set($branch->id);
        }

        return $branch;
    }
}
