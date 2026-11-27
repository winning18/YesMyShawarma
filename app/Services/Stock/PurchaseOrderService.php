<?php

namespace App\Services\Stock;

use App\Models\MenuItem;
use App\Models\ProductPack;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Buying stock at a pharmacy or shop (owner-approved 2026-11-27; plan step
 * R4). A purchase order is drafted, marked ordered, then received, in full
 * or in parts; a delivery with no order is recorded as one received at once.
 *
 * - Receiving adds stock in the product's own unit (a box of 100 received:
 *   100 tablets), recorded against the order (stock_movements source).
 * - Receiving at a new cost updates the product's (and the pack's) cost
 *   price to the latest (owner); each sale keeps the cost it was sold at.
 * - At a pharmacy, a medicine is received as a batch: its number and expiry
 *   date (BatchService, R5); an expiry that's already past is refused.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly ProductStockService $stock,
        private readonly StockService $movements,
    ) {}

    /**
     * @param  list<array{menu_item_id: int|string, pack_id?: int|string|null, quantity: int|string, unit_cost: string|float|int}>  $lines  unit_cost in cedis, per product or per pack
     */
    public function create(int $branchId, ?int $supplierId, array $lines, User $by, ?string $note = null): PurchaseOrder
    {
        return DB::transaction(function () use ($branchId, $supplierId, $lines, $by, $note) {
            $order = PurchaseOrder::create([
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'reference' => $this->reference(),
                'status' => PurchaseOrder::DRAFT,
                'created_by' => $by->id,
                'note' => $note,
            ]);
            $this->replaceLines($order, $lines);

            return $order->fresh('items');
        });
    }

    /** @param  list<array{menu_item_id: int|string, pack_id?: int|string|null, quantity: int|string, unit_cost: string|float|int}>  $lines */
    public function update(PurchaseOrder $order, ?int $supplierId, array $lines, ?string $note): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::DRAFT) {
            throw ValidationException::withMessages(['lines' => __('Only a draft can be changed. This one is :status.', ['status' => $this->statusLabel($order->status)])]);
        }

        return DB::transaction(function () use ($order, $supplierId, $lines, $note) {
            $order->update(['supplier_id' => $supplierId, 'note' => $note]);
            $order->items()->delete();
            $this->replaceLines($order, $lines);

            return $order->fresh('items');
        });
    }

    public function markOrdered(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::DRAFT) {
            throw ValidationException::withMessages(['status' => __('Only a draft can be marked as ordered.')]);
        }
        $order->update(['status' => PurchaseOrder::ORDERED, 'ordered_at' => now()]);

        return $order;
    }

    /**
     * What arrived: each line's quantity (packs, or units for a unit line).
     *
     * @param  array<int, int|string>  $received  purchase_order_item_id => quantity arrived now
     * @param  array<int, array{batch_number?: ?string, expiry_date?: ?string}>  $batches  purchase_order_item_id => its batch (medicines at a pharmacy)
     */
    public function receive(PurchaseOrder $order, array $received, User $by, array $batches = []): PurchaseOrder
    {
        if (! $order->isOpen()) {
            throw ValidationException::withMessages(['received' => __('This order is :status; nothing more can be received on it.', ['status' => $this->statusLabel($order->status)])]);
        }

        return DB::transaction(function () use ($order, $received, $by, $batches) {
            $any = false;
            foreach ($order->items()->with(['menuItem', 'pack'])->lockForUpdate()->get() as $line) {
                $quantity = (int) ($received[$line->id] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }
                if ($quantity > $line->outstanding()) {
                    throw ValidationException::withMessages(['received' => __(':name: :quantity is more than the :left still to come. Enter what actually arrived.', ['name' => $this->lineName($line), 'quantity' => $quantity, 'left' => $line->outstanding()])]);
                }

                $item = $this->stock->itemFor($line->menuItem, $order->branch_id, $by);
                $batch = $batches[$line->id] ?? [];
                if (app(BatchService::class)->tracks($line->menuItem)) {
                    $this->assertBatch($this->lineName($line), $batch);
                }
                $this->movements->record($item, $by, StockMovement::TYPE_RECEIVED, $quantity * $line->units, [StockMovement::SOURCE_PURCHASE_ORDER, $order->id], __('Received on :reference', ['reference' => $order->reference]).(($batch['batch_number'] ?? null) ? ' · '.__('batch :batch', ['batch' => $batch['batch_number']]) : ''));
                if (app(BatchService::class)->tracks($line->menuItem)) {
                    app(BatchService::class)->receive($item, $quantity * $line->units, $batch['batch_number'] ?? null, $batch['expiry_date'] ?? null, $line->id);
                }
                $line->increment('quantity_received', $quantity);
                $this->updateCost($line);
                $any = true;
            }

            if (! $any) {
                throw ValidationException::withMessages(['received' => __('Enter how many of at least one item arrived.')]);
            }

            $done = $order->items()->get()->every(fn (PurchaseOrderItem $l) => $l->outstanding() === 0);
            $order->update([
                'status' => $done ? PurchaseOrder::RECEIVED : PurchaseOrder::PART_RECEIVED,
                'ordered_at' => $order->ordered_at ?? now(),
                'received_at' => now(),
                'received_by' => $by->id,
            ]);

            return $order->fresh('items');
        });
    }

    /**
     * A delivery that came with no order: recorded as an order received in full.
     *
     * @param  list<array{menu_item_id: int|string, pack_id?: int|string|null, quantity: int|string, unit_cost: string|float|int, batch_number?: ?string, expiry_date?: ?string}>  $lines
     */
    public function receiveWithoutOrder(int $branchId, ?int $supplierId, array $lines, User $by, ?string $note = null): PurchaseOrder
    {
        return DB::transaction(function () use ($branchId, $supplierId, $lines, $by, $note) {
            $lines = array_values(array_filter($lines, fn ($l) => (int) ($l['quantity'] ?? 0) > 0));
            $order = $this->create($branchId, $supplierId, $lines, $by, $note);
            $order->update(['status' => PurchaseOrder::ORDERED, 'ordered_at' => now()]);
            $items = $order->items()->orderBy('id')->get()->values();

            return $this->receive(
                $order,
                $items->mapWithKeys(fn (PurchaseOrderItem $l) => [$l->id => $l->quantity])->all(),
                $by,
                $items->mapWithKeys(fn (PurchaseOrderItem $l, int $i) => [$l->id => ['batch_number' => $lines[$i]['batch_number'] ?? null, 'expiry_date' => $lines[$i]['expiry_date'] ?? null]])->all(),
            );
        });
    }

    /** @param  array{batch_number?: ?string, expiry_date?: ?string}  $batch */
    private function assertBatch(string $name, array $batch): void
    {
        $expiry = $batch['expiry_date'] ?? null;
        if (! $expiry || ! strtotime($expiry)) {
            throw ValidationException::withMessages(['received' => __(':name is a medicine: enter its expiry date (and batch number) from the box.', ['name' => $name])]);
        }
        if ($expiry < now('Africa/Accra')->toDateString()) {
            throw ValidationException::withMessages(['received' => __(':name: the expiry date :date has already passed. Expired stock can\'t be received; return it to the supplier.', ['name' => $name, 'date' => $expiry])]);
        }
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrder::DRAFT, PurchaseOrder::ORDERED], true)) {
            throw ValidationException::withMessages(['status' => __('Only a draft or an order with nothing received yet can be cancelled.')]);
        }
        $order->update(['status' => PurchaseOrder::CANCELLED]);

        return $order;
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            PurchaseOrder::DRAFT => __('Draft'),
            PurchaseOrder::ORDERED => __('Ordered'),
            PurchaseOrder::PART_RECEIVED => __('Part received'),
            PurchaseOrder::RECEIVED => __('Received'),
            PurchaseOrder::CANCELLED => __('Cancelled'),
            default => $status,
        };
    }

    public function lineName(PurchaseOrderItem $line): string
    {
        return ($line->menuItem?->name ?? __('A product')).($line->pack ? ' ('.$line->pack->name.')' : '');
    }

    /** @param  list<array{menu_item_id: int|string, pack_id?: int|string|null, quantity: int|string, unit_cost: string|float|int}>  $lines */
    private function replaceLines(PurchaseOrder $order, array $lines): void
    {
        $lines = array_values(array_filter($lines, fn ($l) => (int) ($l['quantity'] ?? 0) > 0));
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('Add at least one product with a quantity.')]);
        }

        $total = 0;
        foreach ($lines as $i => $line) {
            $product = MenuItem::find((int) $line['menu_item_id']);
            $pack = ($line['pack_id'] ?? null) ? ProductPack::where('menu_item_id', $product?->id)->find((int) $line['pack_id']) : null;
            if (! $product || (($line['pack_id'] ?? null) && ! $pack)) {
                throw ValidationException::withMessages(["lines.{$i}" => __('Line :n: that product or pack no longer exists. Remove the line.', ['n' => $i + 1])]);
            }
            if (! is_numeric($line['unit_cost'] ?? null) || $line['unit_cost'] < 0) {
                throw ValidationException::withMessages(["lines.{$i}" => __('Line :n: the cost must be an amount in cedis, e.g. 28.00.', ['n' => $i + 1])]);
            }

            $cost = Money::toPesewas($line['unit_cost']);
            $order->items()->create([
                'menu_item_id' => $product->id,
                'product_pack_id' => $pack?->id,
                'quantity' => (int) $line['quantity'],
                'units' => $pack ? $pack->units : 1,
                'unit_cost' => $cost,
            ]);
            $total += $cost * (int) $line['quantity'];
        }
        $order->update(['total' => $total]);
    }

    /** The latest cost becomes the product's (and the pack's) cost price (owner). */
    private function updateCost(PurchaseOrderItem $line): void
    {
        if ($line->pack) {
            $line->pack->update(['cost_price' => $line->unit_cost]);
            $line->menuItem->update(['cost_price' => (int) round($line->unit_cost / max(1, $line->units))]);
        } else {
            $line->menuItem->update(['cost_price' => $line->unit_cost]);
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'PO-'.now('Africa/Accra')->format('ymd').'-'.strtoupper(Str::random(4));
        } while (PurchaseOrder::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }
}
