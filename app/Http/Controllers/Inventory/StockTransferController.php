<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Services\Branches\BranchContext;
use App\Services\Stock\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Stock → Transfers (R4, 2026-11-27): send stock to another branch; the
 * other branch confirms what arrived. Pharmacies and shops; stock.manage at
 * the branch doing it. StockTransferService does the work.
 */
class StockTransferController extends Controller
{
    public function index(BranchContext $context, StockTransferService $transfers): View|RedirectResponse
    {
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        return view('dashboard.inventory.transfers.index', ['branch' => $branch, 'transfers' => $transfers->forBranch($branch->id)]);
    }

    public function create(BranchContext $context): View|RedirectResponse
    {
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        return view('dashboard.inventory.transfers.create', ['branch' => $branch, 'branches' => Branch::whereKeyNot($branch->id)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, BranchContext $context, StockTransferService $transfers): RedirectResponse
    {
        Gate::authorize('stock.manage');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $v = $request->validate([
            'to_branch_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'lines.*.units' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], ['lines.required' => __('Add at least one product and how many to send.'), 'to_branch_id.required' => __('Choose the branch to send to.')]);

        $transfer = $transfers->send($branch->id, (int) $v['to_branch_id'], $v['lines'], $request->user(), $v['note'] ?? null);

        return redirect()->route('dashboard.inventory.transfers.show', $transfer)->with('status', __('Sent: the stock has left this branch. :branch confirms when it arrives.', ['branch' => $transfer->toBranch->name]));
    }

    public function show(StockTransfer $transfer, BranchContext $context): View
    {
        Gate::authorize('stock.manage');
        abort_unless(in_array($context->id(), [$transfer->from_branch_id, $transfer->to_branch_id], true), 404);

        return view('dashboard.inventory.transfers.show', [
            'transfer' => $transfer->load(['items.menuItem', 'fromBranch', 'toBranch', 'sender:id,name', 'receiver:id,name']),
            'canReceive' => $transfer->status === StockTransfer::SENT && $context->id() === $transfer->to_branch_id,
            'canCancel' => $transfer->status === StockTransfer::SENT && $context->id() === $transfer->from_branch_id,
        ]);
    }

    public function receive(Request $request, StockTransfer $transfer, BranchContext $context, StockTransferService $transfers): RedirectResponse
    {
        Gate::authorize('stock.manage');
        abort_unless($context->id() === $transfer->to_branch_id, 404);
        $v = $request->validate([
            'received' => ['required', 'array'], 'received.*' => ['required', 'integer', 'min:0'],
            'batches' => ['array'], 'batches.*.batch_number' => ['nullable', 'string', 'max:60'], 'batches.*.expiry_date' => ['nullable', 'date'],
        ]);

        $transfers->receive($transfer, $v['received'], $request->user(), $v['batches'] ?? []);

        return back()->with('status', __('Received: stock added to this branch.'));
    }

    public function cancel(StockTransfer $transfer, BranchContext $context, StockTransferService $transfers, Request $request): RedirectResponse
    {
        Gate::authorize('stock.manage');
        abort_unless($context->id() === $transfer->from_branch_id, 404);

        $transfers->cancel($transfer, $request->user());

        return back()->with('status', __('Transfer cancelled: the stock is back at this branch.'));
    }
}
