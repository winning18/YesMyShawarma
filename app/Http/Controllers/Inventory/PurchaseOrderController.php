<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Branches\BranchContext;
use App\Services\Stock\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Stock → Purchase orders and receiving (R4, 2026-11-27): draft, order,
 * receive in full or parts, or record a delivery that came with no order.
 * Pharmacies and shops; stock.manage. PurchaseOrderService does the work.
 */
class PurchaseOrderController extends Controller
{
    public function index(BranchContext $context, PurchaseOrderService $orders): View|RedirectResponse
    {
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        return view('dashboard.inventory.purchase-orders.index', [
            'branch' => $branch,
            'orders' => PurchaseOrder::where('branch_id', $branch->id)->with('supplier:id,name')->withCount('items')->latest('id')->paginate(30),
            'label' => fn (string $status) => $orders->statusLabel($status),
        ]);
    }

    public function create(Request $request, BranchContext $context): View|RedirectResponse
    {
        Gate::authorize('stock.manage');
        // Stock belongs to a branch: choose one first, as on every stock page.
        if (! $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        return view('dashboard.inventory.purchase-orders.create', [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'receiveNow' => $request->boolean('receive'),
        ]);
    }

    public function store(Request $request, BranchContext $context, PurchaseOrderService $orders): RedirectResponse
    {
        Gate::authorize('stock.manage');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $v = $this->validated($request);
        $order = $request->boolean('receive_now')
            ? $orders->receiveWithoutOrder($branch->id, $v['supplier_id'] ?? null, $v['lines'], $request->user(), $v['note'] ?? null)
            : $orders->create($branch->id, $v['supplier_id'] ?? null, $v['lines'], $request->user(), $v['note'] ?? null);

        return redirect()->route('dashboard.inventory.purchase-orders.show', $order)
            ->with('status', $request->boolean('receive_now') ? __('Delivery recorded: stock added.') : __('Purchase order :reference saved as a draft.', ['reference' => $order->reference]));
    }

    public function show(PurchaseOrder $purchaseOrder, PurchaseOrderService $orders): View
    {
        Gate::authorize('stock.manage');

        return view('dashboard.inventory.purchase-orders.show', [
            'order' => $purchaseOrder->load(['items.menuItem', 'items.pack', 'supplier', 'creator:id,name', 'receiver:id,name']),
            'statusLabel' => $orders->statusLabel($purchaseOrder->status),
            'lineName' => fn ($line) => $orders->lineName($line),
        ]);
    }

    public function ordered(PurchaseOrder $purchaseOrder, PurchaseOrderService $orders): RedirectResponse
    {
        Gate::authorize('stock.manage');
        $orders->markOrdered($purchaseOrder);

        return back()->with('status', __('Marked as ordered.'));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $orders): RedirectResponse
    {
        Gate::authorize('stock.manage');
        $v = $request->validate([
            'received' => ['required', 'array'], 'received.*' => ['nullable', 'integer', 'min:0'],
            'batches' => ['array'], 'batches.*.batch_number' => ['nullable', 'string', 'max:60'], 'batches.*.expiry_date' => ['nullable', 'date'],
        ]);
        $orders->receive($purchaseOrder, array_map('intval', array_filter($v['received'], fn ($q) => $q !== null)), $request->user(), $v['batches'] ?? []);

        return back()->with('status', __('Received: stock added.'));
    }

    public function cancel(PurchaseOrder $purchaseOrder, PurchaseOrderService $orders): RedirectResponse
    {
        Gate::authorize('stock.manage');
        $orders->cancel($purchaseOrder);

        return back()->with('status', __('Purchase order cancelled.'));
    }

    /** @return array{supplier_id?: ?int, note?: ?string, lines: list<array<string, mixed>>} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.menu_item_id' => ['required', 'integer'],
            'lines.*.pack_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:60'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ], [
            'lines.required' => __('Add at least one product.'),
            'lines.*.quantity.min' => __('Each line needs a quantity of 1 or more.'),
            'lines.*.unit_cost.required' => __('Each line needs its cost in cedis (0 if free).'),
        ]);
    }
}
