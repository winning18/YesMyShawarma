<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuItemRecipeItemRequest;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeItem;
use App\Models\Option;
use App\Services\Branches\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * What a menu item's own base recipe, or a customer-chosen option, consumes
 * from the *current* branch's stock — menu.edit_content, same permission as
 * every other menu-shape change. Recipes are per-branch (MenuItemRecipeItem's
 * docblock explains why), so every action here is scoped to
 * BranchContext::id() — a branch must actually be selected (owner's
 * cross-branch view has none) for any of these to make sense, same
 * constraint StockItemController::store() already has for owner.
 */
class RecipeManagementController extends Controller
{
    public function storeForMenuItem(StoreMenuItemRecipeItemRequest $request, MenuItem $menuItem, BranchContext $context): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        MenuItemRecipeItem::create([
            'branch_id' => $context->id(),
            'source_type' => MenuItemRecipeItem::SOURCE_MENU_ITEM,
            'source_menu_item_id' => $menuItem->id,
            'stock_item_id' => $validated['stock_item_id'],
            'quantity' => $validated['quantity'],
        ]);

        return back()->with('status', __('Recipe updated.'));
    }

    public function storeForOption(StoreMenuItemRecipeItemRequest $request, Option $option, BranchContext $context): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $validated = $request->validated();

        MenuItemRecipeItem::create([
            'branch_id' => $context->id(),
            'source_type' => MenuItemRecipeItem::SOURCE_OPTION,
            'source_option_id' => $option->id,
            'stock_item_id' => $validated['stock_item_id'],
            'quantity' => $validated['quantity'],
        ]);

        return back()->with('status', __('Recipe updated.'));
    }

    public function destroy(MenuItemRecipeItem $recipeItem): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $recipeItem->delete();

        return back()->with('status', __('Removed from recipe.'));
    }
}
