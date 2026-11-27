<?php

namespace App\Services\Stock;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock moved between a pharmacy's or shop's branches (owner-approved
 * 2026-11-27; plan step R4): it leaves the sending branch when sent, and
 * arrives at the other branch when someone there confirms what came (which
 * may be less). Cancelled before it arrives, it goes back to the sender.
 */
class StockTransferService
{
    public function __construct(
        private readonly ProductStockService $stock,
        private readonly StockService $movements,
    ) {}

    /** @param  list<array{menu_item_id: int|string, units: int|string}>  $lines */
    public function send(int $fromBranchId, int $toBranchId, array $lines, User $by, ?string $note = null): StockTransfer
    {
        if ($fromBranchId === $toBranchId || ! Branch::whereKey($toBranchId)->exists()) {
            throw ValidationException::withMessages(['to_branch_id' => __('Choose another branch to send to.')]);
        }
        $lines = array_values(array_filter($lines, fn ($l) => (int) ($l['units'] ?? 0) > 0));
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('Add at least one product and how many to send.')]);
        }

        return DB::transaction(function () use ($fromBranchId, $toBranchId, $lines, $by, $note) {
            $transfer = StockTransfer::create(['from_branch_id' => $fromBranchId, 'to_branch_id' => $toBranchId, 'status' => StockTransfer::SENT, 'sent_by' => $by->id, 'sent_at' => now(), 'note' => $note]);
            $to = Branch::find($toBranchId)->name;

            foreach ($lines as $line) {
                $product = MenuItem::findOrFail((int) $line['menu_item_id']);
                $transfer->items()->create(['menu_item_id' => $product->id, 'units' => (int) $line['units']]);
                $from = $this->stock->itemFor($product, $fromBranchId, $by);
                if (app(BatchService::class)->tracks($product)) {
                    app(BatchService::class)->assertSellable($product, $fromBranchId);
                    app(BatchService::class)->take($from, (int) $line['units']);
                }
                $this->movements->record($from, $by, StockMovement::TYPE_TRANSFER_OUT, -(int) $line['units'], [StockMovement::SOURCE_TRANSFER, $transfer->id], __('Sent to :branch', ['branch' => $to]));
            }

            return $transfer->fresh('items');
        });
    }

    /**
     * @param  array<int, int|string>  $received  stock_transfer_item_id => units that arrived
     * @param  array<int, array{batch_number?: ?string, expiry_date?: ?string}>  $batches  a medicine's batch, from the boxes (R5)
     */
    public function receive(StockTransfer $transfer, array $received, User $by, array $batches = []): StockTransfer
    {
        $this->assertSent($transfer);

        return DB::transaction(function () use ($transfer, $received, $by, $batches) {
            $from = $transfer->fromBranch->name;
            foreach ($transfer->items()->with('menuItem')->get() as $line) {
                $units = (int) ($received[$line->id] ?? $line->units);
                if ($units < 0 || $units > $line->units) {
                    throw ValidationException::withMessages(['received' => __(':name: enter between 0 and :units.', ['name' => $line->menuItem->name, 'units' => $line->units])]);
                }
                $line->update(['received_units' => $units]);
                if ($units > 0) {
                    $item = $this->stock->itemFor($line->menuItem, $transfer->to_branch_id, $by);
                    if (app(BatchService::class)->tracks($line->menuItem)) {
                        $batch = $batches[$line->id] ?? [];
                        if (! ($batch['expiry_date'] ?? null)) {
                            throw ValidationException::withMessages(['received' => __(':name is a medicine: enter its expiry date (and batch number) from the box.', ['name' => $line->menuItem->name])]);
                        }
                        app(BatchService::class)->receive($item, $units, $batch['batch_number'] ?? null, $batch['expiry_date']);
                    }
                    $this->movements->record($item, $by, StockMovement::TYPE_TRANSFER_IN, $units, [StockMovement::SOURCE_TRANSFER, $transfer->id], __('Received from :branch', ['branch' => $from]));
                }
            }
            $transfer->update(['status' => StockTransfer::RECEIVED, 'received_by' => $by->id, 'received_at' => now()]);

            return $transfer->fresh('items');
        });
    }

    public function cancel(StockTransfer $transfer, User $by): StockTransfer
    {
        $this->assertSent($transfer);

        return DB::transaction(function () use ($transfer, $by) {
            foreach ($transfer->items()->with('menuItem')->get() as $line) {
                $item = $this->stock->itemFor($line->menuItem, $transfer->from_branch_id, $by);
                $this->movements->record($item, $by, StockMovement::TYPE_TRANSFER_IN, $line->units, [StockMovement::SOURCE_TRANSFER, $transfer->id], __('Transfer cancelled: back in'));
                if (app(BatchService::class)->tracks($line->menuItem)) {
                    app(BatchService::class)->refill($item, $line->units);
                }
            }
            $transfer->update(['status' => StockTransfer::CANCELLED]);

            return $transfer;
        });
    }

    /** @return Collection<int, StockTransfer> to or from the branch, newest first */
    public function forBranch(int $branchId): Collection
    {
        return StockTransfer::where(fn ($q) => $q->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId))
            ->with(['fromBranch:id,name', 'toBranch:id,name', 'sender:id,name'])->withCount('items')->latest('id')->limit(100)->get();
    }

    private function assertSent(StockTransfer $transfer): void
    {
        if ($transfer->status !== StockTransfer::SENT) {
            throw ValidationException::withMessages(['transfer' => __('This transfer has already been received or cancelled.')]);
        }
    }
}
