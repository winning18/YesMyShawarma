<?php

namespace App\Services\Pos;

use App\Models\HeldSale;
use App\Models\Shift;
use App\Models\User;
use App\Services\Cart\PosCartService;
use App\Services\Shifts\CounterService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Held sales at a pharmacy's or shop's till (owner-approved 2026-11-27;
 * plan step R3): park the sale in progress ("the customer went to get
 * money") and serve the next person; any cashier at the branch recalls it.
 * Cleared when its till closes (owner): clearForClosedTill().
 */
class HeldSaleService
{
    public function __construct(private readonly CounterService $counters) {}

    public function hold(PosCartService $cart, User $by, int $branchId, ?string $label): HeldSale
    {
        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['hold' => __('There\'s nothing to hold: add items to the sale first.')]);
        }

        $held = HeldSale::create([
            'branch_id' => $branchId,
            'terminal_id' => $this->counters->currentAt($branchId)?->id,
            'user_id' => $by->id,
            'label' => ($label = trim((string) $label)) === '' ? null : mb_substr($label, 0, 60),
            'cart' => $cart->export(),
        ]);
        $cart->clear();
        // A pharmacy's approval belongs to the sale on screen (R5): held, it goes.
        app(PharmacySaleService::class)->forget();

        return $held;
    }

    /** @return Collection<int, HeldSale> newest first */
    public function forBranch(int $branchId): Collection
    {
        return HeldSale::where('branch_id', $branchId)->with('user:id,name')->latest()->get();
    }

    public function recall(HeldSale $held, PosCartService $cart): void
    {
        if (! $cart->isEmpty()) {
            throw ValidationException::withMessages(['hold' => __('Finish or hold the sale on screen first, then recall this one.')]);
        }

        $cart->restore($held->cart);
        $held->delete();
        app(PharmacySaleService::class)->forget();
    }

    public function discard(HeldSale $held): void
    {
        $held->delete();
    }

    /**
     * When a till closes: its own held sales (a counter's), or every held
     * sale at a branch with one shared till.
     */
    public function clearForClosedTill(Shift $shift): int
    {
        return HeldSale::where('branch_id', $shift->branch_id)
            ->when($shift->terminal_id, fn ($q) => $q->where('terminal_id', $shift->terminal_id))
            ->delete();
    }
}
