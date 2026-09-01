<?php

namespace App\Services\Stock;

use App\Exceptions\StockException;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Notifications\StockAlertNotifier;
use Illuminate\Support\Facades\DB;

/**
 * quantity on stock_items is a denormalised running total — stock_movements
 * is the source of truth, same relationship as orders/order_events. Every
 * change to quantity happens through restock()/recordSale() so the two
 * never drift; nothing else is allowed to write to stock_items.quantity
 * directly (see updateItem(), which deliberately excludes it).
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

    public function recordSale(StockItem $item, User $actor, float $quantity, ?int $shiftId = null, ?string $note = null): StockMovement
    {
        $itemToAlert = null;

        $movement = DB::transaction(function () use ($item, $actor, $quantity, $shiftId, $note, &$itemToAlert) {
            $locked = StockItem::withoutGlobalScopes()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($quantity > $locked->quantity) {
                throw StockException::insufficientStock((float) $locked->quantity, $locked->unit);
            }

            $locked->decrement('quantity', $quantity);

            $movement = $locked->movements()->create([
                'type' => StockMovement::TYPE_SALE,
                'quantity' => $quantity,
                'actor_id' => $actor->id,
                'shift_id' => $shiftId,
                'note' => $note,
            ]);

            // De-bounced: only send once per "crossed below threshold"
            // episode — restock() clears low_stock_alerted_at once it's
            // back at/above threshold, re-arming this for next time.
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
