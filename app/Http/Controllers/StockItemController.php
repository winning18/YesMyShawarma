<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockItemRequest;
use App\Http\Requests\UpdateStockItemRequest;
use App\Models\StockItem;
use App\Services\Branches\BranchContext;
use App\Services\Stock\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin side of stock management (create/edit items, set quantities and
 * low-stock thresholds) — stock.manage. Scoped to whichever branch is
 * currently selected via the same branch switcher every other dashboard
 * page uses (BranchScope on StockItem), exactly like orders/menu editing —
 * stock has no cross-branch aggregate view the way Performance/Refunds do,
 * since nothing in the requirements asked for owner to see every branch's
 * stock at once.
 *
 * Owner's session branch can legitimately be null (BranchContext::id() —
 * the cross-branch view), which BranchScope treats as "no filter" so
 * index()/edit() already work unchanged. Creating a *new* item has no
 * existing branch_id to fall back on though, so store() needs an explicit
 * branch when the actor is owner — same shape as WorkingHoursController's
 * owner-picks-a-branch selector.
 */
class StockItemController extends Controller
{
    public function index(BranchContext $context): View
    {
        Gate::authorize('stock.manage');

        $isOwner = $context->hasRoleAtAnyBranch(request()->user(), 'owner');

        return view('dashboard.stock.index', [
            'items' => StockItem::with(['createdBy', 'branch'])->orderBy('name')->get(),
            'showBranchColumn' => $isOwner && $context->id() === null,
        ]);
    }

    public function create(Request $request, BranchContext $context): View
    {
        Gate::authorize('stock.manage');

        $isOwner = $context->hasRoleAtAnyBranch($request->user(), 'owner');

        return view('dashboard.stock.create', [
            'branches' => $isOwner ? $context->selectableBranchesFor($request->user())->sortBy('name')->values() : null,
        ]);
    }

    public function store(StoreStockItemRequest $request, StockService $stock, BranchContext $context): RedirectResponse
    {
        Gate::authorize('stock.manage');

        $isOwner = $context->hasRoleAtAnyBranch($request->user(), 'owner');
        $branchId = $isOwner ? $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id']])['branch_id'] : $context->id();

        abort_unless($branchId, 404);

        $validated = $request->validated();

        $item = $stock->createItem(
            branchId: $branchId,
            creator: $request->user(),
            name: $validated['name'],
            unit: $validated['unit'],
            lowStockThreshold: $validated['low_stock_threshold'],
            initialQuantity: $validated['quantity'],
        );

        return redirect()->route('dashboard.stock.index')
            ->with('status', __(':name has been added to stock.', ['name' => $item->name]));
    }

    public function edit(StockItem $stockItem): View
    {
        Gate::authorize('stock.manage');

        return view('dashboard.stock.edit', ['item' => $stockItem]);
    }

    public function update(UpdateStockItemRequest $request, StockItem $stockItem, StockService $stock): RedirectResponse
    {
        Gate::authorize('stock.manage');

        $validated = $request->validated();

        $stock->updateItem($stockItem, $validated['name'], $validated['unit'], $validated['low_stock_threshold']);

        return redirect()->route('dashboard.stock.index')
            ->with('status', __(':name has been updated.', ['name' => $stockItem->name]));
    }

    public function restock(Request $request, StockItem $stockItem, StockService $stock): RedirectResponse
    {
        Gate::authorize('stock.manage');

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $stock->restock($stockItem, $request->user(), $validated['quantity'], $validated['note'] ?? null);

        return redirect()->route('dashboard.stock.index')
            ->with('status', __(':name has been restocked.', ['name' => $stockItem->name]));
    }
}
