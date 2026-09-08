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
     * Only ever applies to an option from a multi-select group — see
     * buildOptionRows().
     */
    public const MAX_OPTION_QUANTITY = 10;

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

        $unitPrice = (int) $menuItem->base_price;

        $optionRows = $this->buildOptionRows($menuItem, $itemData->optionQuantities);

        // A single-select option (Size, Spice level — always exactly one)
        // prices the same way it always has: folded into the per-unit
        // price and multiplied by the line's own quantity. A multi-select
        // option's own quantity is a fixed amount for the whole line,
        // chosen independently of how many units are in it — "extra
        // cheese x2" means 2 total, not 2 per unit — so it's added once,
        // never multiplied by $itemData->quantity.
        $perUnitOptionsTotal = array_sum(array_map(
            fn (array $row) => $row['adjustable'] ? 0 : $row['price_delta_snapshot'],
            $optionRows
        ));
        $fixedOptionsTotal = array_sum(array_map(
            fn (array $row) => $row['adjustable'] ? $row['price_delta_snapshot'] * $row['quantity'] : 0,
            $optionRows
        ));

        $lineTotal = ($unitPrice + $perUnitOptionsTotal) * $itemData->quantity + $fixedOptionsTotal;

        return [
            'menu_item_id' => $menuItem->id,
            'name_snapshot' => $menuItem->name,
            'unit_price_snapshot' => $unitPrice,
            'quantity' => $itemData->quantity,
            'line_total' => $lineTotal,
            'notes' => $itemData->notes,
            'options' => $optionRows,
            // Live, not snapshotted — display-only (cart page), never
            // persisted. OrderCreationService reads specific keys off this
            // same row and doesn't touch this one, so it can't leak into
            // order_items the way schema.md's snapshotting rule warns against.
            'image_url' => $menuItem->imageUrl(),
        ];
    }

    /**
     * @param  array<int, int>  $optionQuantities  option_id => quantity
     * @return list<array<string, mixed>>
     */
    private function buildOptionRows(MenuItem $menuItem, array $optionQuantities): array
    {
        $optionGroups = $menuItem->optionGroups;

        if (empty($optionQuantities)) {
            $this->assertGroupCountsSatisfied($optionGroups, collect());

            return [];
        }

        $optionIds = array_keys($optionQuantities);

        $selected = Option::where('is_active', true)
            ->whereIn('id', $optionIds)
            ->get();

        if ($selected->count() !== count($optionIds)) {
            throw OrderPlacementException::invalidOptionSelection('one or more selected options are unavailable');
        }

        $validGroupIds = $optionGroups->pluck('id')->all();
        $groupsById = $optionGroups->keyBy('id');

        foreach ($selected as $option) {
            if (! in_array($option->option_group_id, $validGroupIds, true)) {
                throw OrderPlacementException::invalidOptionSelection("option \"{$option->name}\" does not belong to this item");
            }
        }

        $this->assertGroupCountsSatisfied($optionGroups, $selected);

        return $selected->map(function (Option $option) use ($optionQuantities, $groupsById) {
            $adjustable = $groupsById[$option->option_group_id]->max_select > 1;

            // A tampered request setting a quantity on a single-select
            // option (e.g. trying to order "2x Large" as a size) is
            // silently corrected to 1 rather than rejected — the group
            // count check above already confirms exactly one option was
            // chosen from that group, which is all a single-select group
            // means.
            $quantity = $adjustable
                ? max(1, min(self::MAX_OPTION_QUANTITY, (int) $optionQuantities[$option->id]))
                : 1;

            return [
                'option_id' => $option->id,
                'name_snapshot' => $option->name,
                'price_delta_snapshot' => $option->price_delta,
                'quantity' => $quantity,
                'adjustable' => $adjustable,
            ];
        })->values()->all();
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
