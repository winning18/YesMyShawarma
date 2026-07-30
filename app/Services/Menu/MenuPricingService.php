<?php

namespace App\Services\Menu;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Option;
use App\Services\Orders\Data\PlaceOrderItemData;
use Illuminate\Support\Collection;

/**
 * The single source of truth for "what does this cart/order line actually
 * cost, and is it a legal selection" — shared by the cart (for a live
 * running total before checkout) and OrderCreationService (for the final,
 * authoritative price at order placement). Extracted so the two never
 * silently drift apart.
 */
class MenuPricingService
{
    /**
     * @param  PlaceOrderItemData[]  $items
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    public function priceItems(Branch $branch, array $items): array
    {
        $rows = [];
        $subtotal = 0;

        foreach ($items as $itemData) {
            $row = $this->priceItem($branch, $itemData);
            $rows[] = $row;
            $subtotal += $row['line_total'];
        }

        return [$rows, $subtotal];
    }

    /**
     * @return array<string, mixed>
     */
    public function priceItem(Branch $branch, PlaceOrderItemData $itemData): array
    {
        if ($itemData->quantity < 1) {
            throw OrderPlacementException::invalidQuantity();
        }

        // OrderCreationService already checks this before it ever reaches
        // pricing, but CartService calls priceItem() directly (for the live
        // cart total) with no such check upstream — without it here, items
        // could be added to and priced in a cart for a branch that's since
        // been deactivated entirely.
        if (! $branch->is_active) {
            throw OrderPlacementException::branchNotAccepting();
        }

        $menuItem = MenuItem::find($itemData->menuItemId);

        if (! $menuItem || ! $menuItem->is_active) {
            throw OrderPlacementException::menuItemUnavailable('Selected item');
        }

        $branchPivot = $branch->menuItems()->find($menuItem->id);

        if (! $branchPivot || ! $branchPivot->pivot->is_available) {
            throw OrderPlacementException::menuItemUnavailable($menuItem->name);
        }

        $unitPrice = (int) ($branchPivot->pivot->price_override ?? $menuItem->base_price);

        $optionRows = $this->buildOptionRows($menuItem, $itemData->optionIds);
        $optionsTotal = array_sum(array_column($optionRows, 'price_delta_snapshot'));

        $lineTotal = ($unitPrice + $optionsTotal) * $itemData->quantity;

        return [
            'menu_item_id' => $menuItem->id,
            'name_snapshot' => $menuItem->name,
            'unit_price_snapshot' => $unitPrice,
            'quantity' => $itemData->quantity,
            'line_total' => $lineTotal,
            'notes' => $itemData->notes,
            'options' => $optionRows,
        ];
    }

    /**
     * @param  int[]  $optionIds
     * @return list<array<string, mixed>>
     */
    private function buildOptionRows(MenuItem $menuItem, array $optionIds): array
    {
        $optionGroups = $menuItem->optionGroups;

        if (empty($optionIds)) {
            $this->assertGroupCountsSatisfied($optionGroups, collect());

            return [];
        }

        $uniqueIds = array_unique($optionIds);

        $selected = Option::where('is_active', true)
            ->whereIn('id', $uniqueIds)
            ->get();

        if ($selected->count() !== count($uniqueIds)) {
            throw OrderPlacementException::invalidOptionSelection('one or more selected options are unavailable');
        }

        $validGroupIds = $optionGroups->pluck('id')->all();

        foreach ($selected as $option) {
            if (! in_array($option->option_group_id, $validGroupIds, true)) {
                throw OrderPlacementException::invalidOptionSelection("option \"{$option->name}\" does not belong to this item");
            }
        }

        $this->assertGroupCountsSatisfied($optionGroups, $selected);

        return $selected->map(fn (Option $option) => [
            'option_id' => $option->id,
            'name_snapshot' => $option->name,
            'price_delta_snapshot' => $option->price_delta,
        ])->values()->all();
    }

    private function assertGroupCountsSatisfied(Collection $optionGroups, Collection $selected): void
    {
        foreach ($optionGroups as $group) {
            $count = $selected->where('option_group_id', $group->id)->count();

            if ($count < $group->min_select || $count > $group->max_select) {
                throw OrderPlacementException::invalidOptionSelection(
                    "\"{$group->name}\" requires between {$group->min_select} and {$group->max_select} selection(s)"
                );
            }

            if ($group->is_required && $count < 1) {
                throw OrderPlacementException::invalidOptionSelection("\"{$group->name}\" is required");
            }
        }
    }
}
