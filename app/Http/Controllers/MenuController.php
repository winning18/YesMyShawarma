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
        ]);
    }

    /**
     * Shared by index() and show() — both require the same active,
     * currently-selected branch and the same "send them to pick one"
     * fallback when that's not true.
     */
    private function resolveBranch(CustomerBranchSelection $selection): Branch|RedirectResponse
    {
        $branch = $selection->current();

        if (! $branch) {
            return redirect()->route('branches.index')->with('status', 'Choose a branch to see its menu.');
        }

        // A branch selected earlier in this session may have been
        // deactivated since — CustomerBranchSelection has no opinion on
        // that (it's a plain "what's selected" accessor), so it's checked
        // at the point of use instead.
        if (! $branch->is_active) {
            return redirect()->route('branches.index')->with('status', 'That branch is no longer available — please choose another.');
        }

        return $branch;
    }
}
