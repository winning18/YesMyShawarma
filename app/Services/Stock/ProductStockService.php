<?php

namespace App\Services\Stock;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Settings\BusinessType;
use Illuminate\Support\Collection;

/**
 * A pharmacy's or shop's stock (owner-approved 2026-11-27; plan step R4):
 * one stock_items row per product per branch, counted in the product's own
 * unit (tablets, pieces), made the first time it's needed. Every change goes
 * through StockService::record(), so stock_movements stays the record and
 * the low-stock alert works as for a restaurant's ingredients.
 *
 * - A sale takes off quantity × units (a box of 100: 100) when the order is
 *   accepted, which a counter sale is as it's paid; cancelled while still
 *   accepted, it comes back (OrderStateMachine, as recipe stock).
 * - Stock may go below zero: the till warns and sells anyway (owner); a
 *   count puts it right.
 * - A returned item goes back on the shelf unless ticked damaged (owner).
 * Restaurants: nothing here runs; their stock is recipes (RecipeStockService).
 */
class ProductStockService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly BusinessType $type,
    ) {}

    public function applies(): bool
    {
        return ! $this->type->isRestaurant();
    }

    /** The branch's stock row for a product, made at 0 if it has none yet. */
    public function itemFor(MenuItem $product, int $branchId, User $actor): StockItem
    {
        $item = StockItem::withoutGlobalScopes()->firstOrCreate(
            ['branch_id' => $branchId, 'menu_item_id' => $product->id],
            ['name' => $product->name, 'unit' => $product->unit_name ?: 'unit', 'quantity' => 0, 'low_stock_threshold' => 0, 'created_by' => $actor->id],
        );

        // The product renamed since: the stock row follows (it's what the alert SMS names).
        if ($item->name !== $product->name) {
            $item->update(['name' => $product->name]);
        }

        return $item;
    }

    /**
     * How many of each product a branch has.
     *
     * @param  list<int>  $menuItemIds
     * @return array<int, float> menu_item_id => on hand (0 when never stocked)
     */
    public function onHand(int $branchId, array $menuItemIds): array
    {
        $levels = StockItem::withoutGlobalScopes()->where('branch_id', $branchId)->whereIn('menu_item_id', $menuItemIds)
            ->pluck('quantity', 'menu_item_id')->map(fn ($q) => (float) $q)->all();

        return array_replace(array_fill_keys($menuItemIds, 0.0), $levels);
    }

    public function deductForOrder(Order $order, int $actorId, ?int $shiftId = null): void
    {
        $this->forOrder($order, $actorId, -1, StockMovement::TYPE_SALE, "Sale {$order->reference}", $shiftId);
    }

    public function restoreForOrder(Order $order, int $actorId): void
    {
        $this->forOrder($order, $actorId, 1, StockMovement::TYPE_RESTOCK, "Back in: sale {$order->reference} cancelled");
    }

    /** A returned line put back on the shelf (CounterReturnService), into the batch it came from. */
    public function returnToShelf(OrderItem $line, int $quantity, Refund $refund, User $actor, ?int $shiftId = null): void
    {
        if (! $this->applies() || ! $line->menuItem) {
            return;
        }

        $units = $quantity * max(1, $line->units);
        $item = $this->itemFor($line->menuItem, $refund->branch_id, $actor);
        $this->stock->record($item, $actor, StockMovement::TYPE_RETURNED, $units, [StockMovement::SOURCE_REFUND, $refund->id], "Returned: sale {$line->order?->reference}", $shiftId);
        if (app(BatchService::class)->tracks($line->menuItem)) {
            app(BatchService::class)->giveBackLine($line, $units);
        }
    }

    private function forOrder(Order $order, int $actorId, int $direction, string $type, string $note, ?int $shiftId = null): void
    {
        if (! $this->applies()) {
            return;
        }

        $actor = User::find($actorId);
        if (! $actor) {
            return;
        }

        $this->unitsByProduct($order)->each(function (int $units, int $menuItemId) use ($order, $actor, $direction, $type, $note, $shiftId) {
            $product = MenuItem::withTrashed()->find($menuItemId);
            if ($product) {
                $item = $this->itemFor($product, $order->branch_id, $actor);
                $this->stock->record($item, $actor, $type, $direction * $units, [StockMovement::SOURCE_ORDER, $order->id], $note, $shiftId);
            }
        });

        // A pharmacy's medicines: which batches (R5), line by line.
        $batches = app(BatchService::class);
        foreach ($order->items()->with('menuItem')->get() as $line) {
            if (! $batches->tracks($line->menuItem)) {
                continue;
            }
            $units = $line->quantity * max(1, $line->units);
            $direction < 0
                ? $batches->take($this->itemFor($line->menuItem, $order->branch_id, $actor), $units, $line)
                : $batches->giveBackLine($line, $units);
        }
    }

    /** @return Collection<int, int> menu_item_id => units sold (free lines included: they left the shelf) */
    private function unitsByProduct(Order $order): Collection
    {
        return $order->items()->get(['menu_item_id', 'quantity', 'units'])
            ->groupBy('menu_item_id')
            ->map(fn (Collection $lines) => (int) $lines->sum(fn (OrderItem $l) => $l->quantity * max(1, $l->units)));
    }
}
