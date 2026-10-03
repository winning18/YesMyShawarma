<?php

namespace App\Services\Stock;

use App\Models\MenuItemRecipeItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Recipe-driven stock deduction — what an order's own items and chosen
 * options consume from the branch's stock, read from menu_item_recipe_items
 * (staff-configured per branch; an item/option with no rows configured
 * simply never touches stock when ordered). See payments.md/orders.md for
 * when this runs (deductForOrder() on acceptance, restoreForOrder() only
 * for a cancellation that never reached 'preparing' — both called from
 * OrderStateMachine, never directly).
 */
class RecipeStockService
{
    public function __construct(private readonly StockService $stock) {}

    public function deductForOrder(Order $order, int $actorId, ?int $shiftId = null): void
    {
        $this->apply($order, $actorId, function (StockItem $item, User $actor, float $quantity) use ($order, $shiftId) {
            $this->stock->recordAutomaticConsumption($item, $actor, $quantity, $shiftId, "Order {$order->reference}");
        });
    }

    /**
     * Only ever called for a cancellation that happened before 'preparing'
     * started (OrderStateMachine) — the ingredients genuinely weren't
     * used yet. A cancellation after prep has begun leaves stock deducted
     * on purpose: that food was likely actually made and wasted, not
     * something to silently un-consume.
     */
    public function restoreForOrder(Order $order, int $actorId): void
    {
        $this->apply($order, $actorId, function (StockItem $item, User $actor, float $quantity) use ($order) {
            $this->stock->restock($item, $actor, $quantity, "Reversed — order {$order->reference} cancelled before preparing.");
        });
    }

    /**
     * @param  callable(StockItem, User, float): void  $action
     */
    private function apply(Order $order, int $actorId, callable $action): void
    {
        $consumption = $this->resolveConsumption($order);

        if ($consumption->isEmpty()) {
            return;
        }

        $actor = User::findOrFail($actorId);

        foreach ($consumption as $stockItemId => $quantity) {
            // withoutGlobalScopes — this order's own branch_id already
            // scoped the recipe lookup below; the acting user's ambient
            // session branch (BranchScope's usual filter) has no bearing
            // on which stock_item a recipe configured at the order's own
            // branch actually points to.
            $item = StockItem::withoutGlobalScopes()->find($stockItemId);

            if ($item) {
                $action($item, $actor, $quantity);
            }
        }
    }

    /**
     * @return Collection<int, float> stock_item_id => total quantity
     */
    private function resolveConsumption(Order $order): Collection
    {
        // withTrashed() on the option relation — Option is soft-deleted
        // (schema.md), and a recipe's multi-select-or-not determination
        // must still resolve correctly for an order whose chosen option
        // has since been removed from the menu.
        $order->loadMissing([
            'items.options.option' => fn ($query) => $query->withTrashed(),
            'items.options.option.optionGroup',
        ]);

        $menuItemIds = $order->items->pluck('menu_item_id')->filter()->unique()->values();
        $optionIds = $order->items->flatMap(fn (OrderItem $item) => $item->options->pluck('option_id'))
            ->filter()->unique()->values();

        if ($menuItemIds->isEmpty() && $optionIds->isEmpty()) {
            return collect();
        }

        $recipeItems = MenuItemRecipeItem::withoutGlobalScopes()
            ->where('branch_id', $order->branch_id)
            ->where(function ($query) use ($menuItemIds, $optionIds) {
                $query->where(
                    fn ($q) => $q->where('source_type', MenuItemRecipeItem::SOURCE_MENU_ITEM)
                        ->whereIn('source_menu_item_id', $menuItemIds)
                )->orWhere(
                    fn ($q) => $q->where('source_type', MenuItemRecipeItem::SOURCE_OPTION)
                        ->whereIn('source_option_id', $optionIds)
                );
            })
            ->get();

        if ($recipeItems->isEmpty()) {
            return collect();
        }

        $recipesByMenuItem = $recipeItems->where('source_type', MenuItemRecipeItem::SOURCE_MENU_ITEM)
            ->groupBy('source_menu_item_id');
        $recipesByOption = $recipeItems->where('source_type', MenuItemRecipeItem::SOURCE_OPTION)
            ->groupBy('source_option_id');

        /** @var Collection<int, float> $consumption */
        $consumption = collect();
        $add = function (int $stockItemId, float $amount) use (&$consumption) {
            $consumption[$stockItemId] = ($consumption[$stockItemId] ?? 0) + $amount;
        };

        foreach ($order->items as $orderItem) {
            foreach ($recipesByMenuItem->get($orderItem->menu_item_id, collect()) as $recipe) {
                $add($recipe->stock_item_id, (float) $recipe->quantity * $orderItem->quantity);
            }

            foreach ($orderItem->options as $optionRow) {
                $recipes = $recipesByOption->get($optionRow->option_id, collect());

                if ($recipes->isEmpty()) {
                    continue;
                }

                // Mirrors MenuPricingService's own per-unit vs fixed-for-
                // the-line split exactly: a multi-select option's chosen
                // quantity is fixed for the whole line ("extra cheese x2"
                // means 2, not 2 per unit), a single-select option's is
                // always 1 and instead scales with the order item's own
                // quantity. option_id can be null if the option was
                // deleted since — already excluded by not matching any
                // $optionIds above, so $recipes would be empty and this
                // is never reached for one.
                $maxSelect = $optionRow->option?->optionGroup?->max_select ?? 1;
                $multiplier = $maxSelect > 1 ? $optionRow->quantity : $orderItem->quantity;

                foreach ($recipes as $recipe) {
                    $add($recipe->stock_item_id, (float) $recipe->quantity * $multiplier);
                }
            }
        }

        return $consumption;
    }
}
