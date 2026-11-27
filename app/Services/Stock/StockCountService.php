<?php

namespace App\Services\Stock;

use App\Models\MenuItem;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock counts at a pharmacy or shop (owner-approved 2026-11-27; plan step
 * R4). Starting one lists every active product (or one category's) with what
 * the system has; staff enter what's on the shelf, saving as they go;
 * finishing corrects each counted product by (counted - expected), so sales
 * made while counting still count. A product left blank isn't changed. One
 * open count per branch.
 */
class StockCountService
{
    public function __construct(
        private readonly ProductStockService $stock,
        private readonly StockService $movements,
    ) {}

    public function start(int $branchId, User $by, ?int $categoryId = null, ?string $note = null): StockCount
    {
        if (StockCount::withoutGlobalScopes()->where('branch_id', $branchId)->where('status', StockCount::OPEN)->exists()) {
            throw ValidationException::withMessages(['count' => __('A count is already open at this branch. Finish or cancel it first.')]);
        }

        return DB::transaction(function () use ($branchId, $by, $categoryId, $note) {
            $count = StockCount::create(['branch_id' => $branchId, 'status' => StockCount::OPEN, 'started_by' => $by->id, 'note' => $note]);

            MenuItem::where('is_active', true)->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))->orderBy('name')
                ->each(function (MenuItem $product) use ($count, $branchId, $by) {
                    $item = $this->stock->itemFor($product, $branchId, $by);
                    $count->items()->create(['stock_item_id' => $item->id, 'expected' => $item->quantity]);
                });

            if ($count->items()->doesntExist()) {
                throw ValidationException::withMessages(['count' => __('There are no active products to count.')]);
            }

            return $count;
        });
    }

    /** @param  array<int, string|int|float|null>  $counted  stock_count_item_id => counted ('' or null = not counted yet) */
    public function save(StockCount $count, array $counted): void
    {
        $this->assertOpen($count);

        foreach ($count->items()->whereIn('id', array_keys($counted))->get() as $line) {
            $value = $counted[$line->id];
            if ($value !== null && $value !== '' && (! is_numeric($value) || $value < 0)) {
                throw ValidationException::withMessages(["counted.{$line->id}" => __(':name: enter the number on the shelf, 0 or more.', ['name' => $line->stockItem->name])]);
            }
            $line->update(['counted' => ($value === null || $value === '') ? null : $value]);
        }
    }

    /** @return int how many products were corrected */
    public function finish(StockCount $count, User $by): int
    {
        $this->assertOpen($count);

        return DB::transaction(function () use ($count, $by) {
            $corrected = 0;
            foreach ($count->items()->whereNotNull('counted')->with('stockItem')->get() as $line) {
                $change = (float) $line->counted - (float) $line->expected;
                if (abs($change) >= 0.005) {
                    $this->movements->record($line->stockItem, $by, StockMovement::TYPE_COUNT, $change, [StockMovement::SOURCE_STOCK_COUNT, $count->id], __('Stock count'));
                    $corrected++;
                }
            }
            $count->update(['status' => StockCount::FINISHED, 'finished_by' => $by->id, 'finished_at' => now()]);

            return $corrected;
        });
    }

    public function cancel(StockCount $count): void
    {
        $this->assertOpen($count);
        $count->update(['status' => StockCount::CANCELLED]);
    }

    private function assertOpen(StockCount $count): void
    {
        if ($count->status !== StockCount::OPEN) {
            throw ValidationException::withMessages(['count' => __('This count is already finished or cancelled.')]);
        }
    }
}
