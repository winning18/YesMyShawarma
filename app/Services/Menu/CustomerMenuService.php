<?php

namespace App\Services\Menu;

use App\Models\Branch;
use App\Models\Category;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Support\Collection;

/**
 * The customer menu as a branch sells it right now: shared by the Menu page
 * and the One-page website theme's home page.
 */
class CustomerMenuService
{
    /**
     * Active categories in menu order, each with the items on sale at
     * $branch (available, active, with their active options), empty
     * categories left out.
     *
     * Ordered by categories.sort_order explicitly, then by each item's own
     * sort_order within that category: grouping a flat item query by
     * category after the fact doesn't respect category order (item
     * sort_order is only unique within its own category).
     *
     * @return Collection<int, array{category: Category, items: Collection}>
     */
    public function categories(Branch $branch): Collection
    {
        return Category::where('is_active', true)
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
    }

    /**
     * The branch whose menu to show: the visitor's own choice while it's
     * still open for business, otherwise the first active branch, picked
     * for them (SOURCE_DEFAULT, so the branch bar says so). Null when no
     * branch is active at all.
     */
    public function branchFor(CustomerBranchSelection $selection): ?Branch
    {
        $branch = $selection->current();

        if ($branch?->is_active) {
            return $branch;
        }

        $branch = Branch::where('is_active', true)->orderBy('id')->first();

        if ($branch) {
            $selection->set($branch->id, CustomerBranchSelection::SOURCE_DEFAULT);
        }

        return $branch;
    }
}
