<?php

namespace App\Services\Stock;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Notifications\StockAlertNotifier;
use Illuminate\Support\Facades\DB;

/**
 * quantity on stock_items is a denormalised running total — stock_movements
 * is the source of truth, same relationship as orders/order_events. Every
 * change to quantity happens through restock()/recordAutomaticConsumption()
 * so the two never drift; nothing else is allowed to write to
 * stock_items.quantity directly (see updateItem(), which deliberately
 * excludes it). There is deliberately no manual "record a sale" entry
 * point any more — stock moves entirely off recipe-driven deduction
 * (RecipeStockService) now that it exists; see schema.md's "Recipes and
 * stock deduction" section.
 */
class StockService
{
    public function __construct(private readonly StockAlertNotifier $alerts) {}

    public function createItem(int $branchId, User $creator, string $name, string $unit, float $lowStockThreshold, float $initialQuantity = 0): StockItem
    {
        return DB::transaction(function () use ($branchId, $creator, $name, $unit, $lowStockThreshold, $initialQuantity) {
            $item = StockItem::create([
                'branch_id' => $branchId,
                'name' => $name,
                'unit' => $unit,
                'quantity' => $initialQuantity,
                'low_stock_threshold' => $lowStockThreshold,
                'created_by' => $creator->id,
            ]);

            if ($initialQuantity > 0) {
                $item->movements()->create([
                    'type' => StockMovement::TYPE_RESTOCK,
                    'quantity' => $initialQuantity,
                    'actor_id' => $creator->id,
                    'note' => 'Initial stock',
                ]);
            }

            return $item;
        });
    }

    /**
     * Deliberately excludes quantity — correcting stock only ever happens
     * through restock(), so stock_movements stays the one source of truth
     * for every change in quantity.
     */
    public function updateItem(StockItem $item, string $name, string $unit, float $lowStockThreshold): StockItem
    {
        $item->update([
            'name' => $name,
            'unit' => $unit,
            'low_stock_threshold' => $lowStockThreshold,
        ]);

        if (! $item->isLowStock()) {
            $item->update(['low_stock_alerted_at' => null]);
        }

        return $item->fresh();
    }

    public function restock(StockItem $item, User $actor, float $quantity, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($item, $actor, $quantity, $note) {
            $locked = StockItem::withoutGlobalScopes()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $locked->increment('quantity', $quantity);

            // Re-arm the low-stock alert once restocked back at/above
            // threshold — a later sale should be able to trigger it again.
            if ($locked->quantity >= $locked->low_stock_threshold) {
                $locked->update(['low_stock_alerted_at' => null]);
            }

            return $locked->movements()->create([
                'type' => StockMovement::TYPE_RESTOCK,
                'quantity' => $quantity,
                'actor_id' => $actor->id,
                'note' => $note,
            ]);
        });
    }

    /**
     * RecipeStockService's own entry point for automatic, recipe-driven
     * deduction when an order is accepted — deliberately never throws on
     * insufficient stock. A kitchen routinely has more of an ingredient on
     * hand than this system has been told about, so blocking an order over
     * a stock-tracking gap would be actively wrong; going negative is
     * itself the visible signal something needs reconciling (isLowStock()
     * already treats any quantity below threshold as low, negative
     * included, so this reuses the exact same alert path restock()'s own
     * re-arming logic expects, no separate "went negative" case needed).
     */
    public function recordAutomaticConsumption(StockItem $item, User $actor, float $quantity, ?int $shiftId = null, ?string $note = null): StockMovement
    {
        $itemToAlert = null;

        $movement = DB::transaction(function () use ($item, $actor, $quantity, $shiftId, $note, &$itemToAlert) {
            $locked = StockItem::withoutGlobalScopes()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $locked->decrement('quantity', $quantity);

            $movement = $locked->movements()->create([
                'type' => StockMovement::TYPE_SALE,
                'quantity' => $quantity,
                'actor_id' => $actor->id,
                'shift_id' => $shiftId,
                'note' => $note,
            ]);

            if ($locked->isLowStock() && $locked->low_stock_alerted_at === null) {
                $locked->update(['low_stock_alerted_at' => now()]);
                $itemToAlert = $locked;
            }

            return $movement;
        });

        // Sent after the transaction commits — never notify on a change
        // that might still roll back.
        if ($itemToAlert) {
            $this->alerts->lowStock($itemToAlert);
        }

        return $movement;
    }
}
