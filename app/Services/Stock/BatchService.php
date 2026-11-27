<?php

namespace App\Services\Stock;

use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\OrderItemBatch;
use App\Models\StockBatch;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Settings\BusinessType;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A pharmacy's medicines kept in batches with expiry dates (owner-approved
 * 2026-11-27; plan step R5). Only for a pharmacy, and only for a product
 * with a medicine class. The stock row's quantity stays the total
 * (StockService); batches say which expiry that stock carries.
 *
 * - Received: a batch with its number and expiry (required for a medicine).
 * - Sold: the batch that expires first goes first (FEFO); which batches a
 *   sale line used is kept (order_item_batches) for recalls and returns.
 *   Stock with no batch (from before batches) is sold after the batches.
 * - Expired stock is never sold (owner): if it's all that's left, the till
 *   refuses and says to write it off. Written off: an `expired` movement.
 * - Put back (a cancelled sale, a return): into the batch it came from.
 *   A count's extra or a cancelled transfer: refills the batches that
 *   were drawn down, earliest expiry first, else a batch with no expiry.
 */
class BatchService
{
    public const DEFAULT_WARNING_DAYS = 90;

    public function __construct(
        private readonly BusinessType $type,
        private readonly StockService $stock,
        private readonly SettingsService $settings,
    ) {}

    public function tracks(?MenuItem $product): bool
    {
        return $product !== null && $product->isMedicine() && $this->type->current() === BusinessType::PHARMACY;
    }

    /** Days before expiry to warn (setting `expiry_warning_days`; to confirm with the pharmacist). */
    public function warningDays(): int
    {
        return max(1, (int) $this->settings->get('expiry_warning_days', (string) self::DEFAULT_WARNING_DAYS));
    }

    public function receive(StockItem $item, float $units, ?string $batchNumber, ?string $expiry, ?int $purchaseOrderItemId = null): StockBatch
    {
        return StockBatch::create([
            'stock_item_id' => $item->id,
            'batch_number' => ($batchNumber = trim((string) $batchNumber)) === '' ? null : $batchNumber,
            'expiry_date' => $expiry ?: null,
            'quantity' => $units,
            'received_quantity' => $units,
            'purchase_order_item_id' => $purchaseOrderItemId,
        ]);
    }

    /**
     * Takes units out, earliest expiry first, never an expired batch.
     *
     * @return array<int, float> stock_batch_id => units taken (the rest came from stock with no batch)
     */
    public function take(StockItem $item, float $units, ?OrderItem $line = null): array
    {
        $taken = [];
        $left = $units;
        foreach ($this->sellableBatches($item) as $batch) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (float) $batch->quantity);
            $batch->update(['quantity' => (float) $batch->quantity - $take]);
            $taken[$batch->id] = $take;
            $left -= $take;
        }

        if ($line) {
            foreach ($taken as $batchId => $take) {
                OrderItemBatch::create(['order_item_id' => $line->id, 'stock_batch_id' => $batchId, 'units' => $take]);
            }
        }

        return $taken;
    }

    /** A sale line's units back into the batches it came from (a cancellation or a return). */
    public function giveBackLine(OrderItem $line, float $units): void
    {
        $left = $units;
        foreach ($line->batches()->with('batch')->latest('id')->get() as $used) {
            if ($left <= 0 || ! $used->batch) {
                break;
            }
            $back = min($left, (float) $used->units);
            $used->batch->update(['quantity' => (float) $used->batch->quantity + $back]);
            $left -= $back;
        }
    }

    /** Units back with no record of their batch: refill what was drawn down, earliest expiry first. */
    public function refill(StockItem $item, float $units): void
    {
        $left = $units;
        foreach ($item->batches()->whereColumn('quantity', '<', 'received_quantity')->orderByRaw('expiry_date is null')->orderBy('expiry_date')->get() as $batch) {
            if ($left <= 0) {
                break;
            }
            $room = (float) $batch->received_quantity - (float) $batch->quantity;
            $put = min($left, $room);
            $batch->update(['quantity' => (float) $batch->quantity + $put]);
            $left -= $put;
        }
        if ($left > 0) {
            StockBatch::create(['stock_item_id' => $item->id, 'quantity' => $left, 'received_quantity' => $left]);
        }
    }

    /**
     * Refuses a sale when expired stock is all that's left (owner: never
     * sell expired). With no batches at all, or stock from before batches,
     * it sells.
     */
    public function assertSellable(MenuItem $product, int $branchId): void
    {
        if (! $this->tracks($product)) {
            return;
        }
        $item = StockItem::withoutGlobalScopes()->where('branch_id', $branchId)->where('menu_item_id', $product->id)->first();
        if (! $item || $item->batches()->doesntExist()) {
            return;
        }

        $sellable = $this->sellableBatches($item)->sum(fn (StockBatch $b) => (float) $b->quantity);
        $inBatches = (float) $item->batches()->sum('quantity');
        $withoutBatch = (float) $item->quantity - $inBatches;
        $expired = $inBatches - $sellable;

        if ($sellable <= 0 && $withoutBatch <= 0 && $expired > 0) {
            throw ValidationException::withMessages(['code' => __('All the :name here has expired, so it can\'t be sold. Write it off under Stock → Expiry.', ['name' => $product->name])]);
        }
    }

    /**
     * A finished count's correction applied to batches: missing units come
     * off the earliest expiry; extra units refill.
     */
    public function adjust(StockItem $item, float $change): void
    {
        $change < 0 ? $this->take($item, -$change) : $this->refill($item, $change);
    }

    public function writeOff(StockBatch $batch, User $by): void
    {
        $units = (float) $batch->quantity;
        if ($units <= 0) {
            throw ValidationException::withMessages(['batch' => __('This batch has nothing left to write off.')]);
        }

        $batch->update(['quantity' => 0]);
        $this->stock->record($batch->stockItem, $by, StockMovement::TYPE_EXPIRED, -$units, [StockMovement::SOURCE_BATCH, $batch->id],
            __('Written off: batch :batch, expiry :date', ['batch' => $batch->batch_number ?? '—', 'date' => $batch->expiry_date?->format('j M Y') ?? '—']));
    }

    /**
     * Batches with stock that have expired or expire within the warning days, soonest first.
     *
     * @return Collection<int, StockBatch>
     */
    public function expiring(int $branchId, ?int $days = null): Collection
    {
        $until = Carbon::now('Africa/Accra')->addDays($days ?? $this->warningDays())->toDateString();

        return StockBatch::query()
            ->whereHas('stockItem', fn ($q) => $q->withoutGlobalScopes()->where('branch_id', $branchId))
            ->where('quantity', '>', 0)->whereNotNull('expiry_date')->where('expiry_date', '<=', $until)
            ->with('stockItem.menuItem')->orderBy('expiry_date')->get();
    }

    /** @return Collection<int, StockBatch> */
    private function sellableBatches(StockItem $item): Collection
    {
        return $item->batches()->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::now('Africa/Accra')->toDateString()))
            ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->get();
    }
}
