<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\ProductPack;
use App\Models\StockItem;
use App\Services\Branches\BranchContext;
use App\Services\Stock\BatchService;
use App\Services\Stock\InventoryReportService;
use App\Services\Stock\ProductStockService;
use App\Services\Stock\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Stock → Stock levels, a product's history, low-stock levels and the stock
 * reports, at the current branch (R4, 2026-11-27). Pharmacies and shops only
 * (`retail`); stock.manage.
 */
class InventoryController extends Controller
{
    public function index(Request $request, BranchContext $context, ProductStockService $stock): View|RedirectResponse
    {
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        $q = trim((string) $request->query('q'));
        $products = MenuItem::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('sku', $q)->orWhere('barcode', $q)))
            ->when($request->integer('category'), fn ($query, $id) => $query->where('category_id', $id))
            ->orderBy('name')->paginate(50)->withQueryString();
        $items = StockItem::where('branch_id', $branch->id)->whereIn('menu_item_id', $products->pluck('id'))->get()->keyBy('menu_item_id');

        return view('dashboard.inventory.index', [
            'branch' => $branch,
            'products' => $products,
            'items' => $items,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function history(MenuItem $menuItem, BranchContext $context, ProductStockService $stock, Request $request): View
    {
        Gate::authorize('stock.manage');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $item = $stock->itemFor($menuItem, $branch->id, $request->user());

        return view('dashboard.inventory.history', [
            'product' => $menuItem,
            'item' => $item,
            'movements' => $item->movements()->with('actor:id,name')->latest('id')->paginate(50),
        ]);
    }

    public function threshold(Request $request, MenuItem $menuItem, BranchContext $context, ProductStockService $stock, StockService $stocks): RedirectResponse
    {
        Gate::authorize('stock.manage');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $v = $request->validate(['low_stock_threshold' => ['required', 'numeric', 'min:0', 'max:1000000']], [
            'low_stock_threshold.required' => __('Enter the level to warn at, e.g. 20. 0 means never warn.'),
        ]);
        $item = $stock->itemFor($menuItem, $branch->id, $request->user());
        $stocks->updateItem($item, $item->name, $item->unit, (float) $v['low_stock_threshold']);

        return back()->with('status', __('Low-stock level for :name saved.', ['name' => $menuItem->name]));
    }

    /** Products for the purchase order and transfer forms: every active one, with packs and cost. */
    public function products(Request $request): JsonResponse
    {
        Gate::authorize('stock.manage');

        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return response()->json(['products' => []]);
        }

        return response()->json(['products' => MenuItem::where('is_active', true)
            ->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('sku', $q)->orWhere('barcode', $q)
                ->orWhereHas('packs', fn ($p) => $p->where('barcode', $q)))
            ->with('packs')->orderBy('name')->limit(20)->get()
            ->map(fn (MenuItem $p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit_name ?: __('unit'),
                'cost' => $p->cost_price,
                // A medicine is received with its batch and expiry (R5).
                'medicine' => app(BatchService::class)->tracks($p),
                'packs' => $p->packs->map(fn (ProductPack $k) => ['id' => $k->id, 'name' => $k->name, 'units' => $k->units, 'cost' => $k->cost_price])->values(),
            ])]);
    }

    public function reports(Request $request, BranchContext $context, InventoryReportService $reports): View|RedirectResponse
    {
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        $from = $request->date('from', 'Y-m-d', 'Africa/Accra')?->startOfDay() ?? Carbon::now('Africa/Accra')->startOfMonth();
        $to = $request->date('to', 'Y-m-d', 'Africa/Accra')?->endOfDay() ?? Carbon::now('Africa/Accra')->endOfDay();
        $days = max(1, min(365, $request->integer('days', 30)));

        return view('dashboard.inventory.reports', [
            'branch' => $branch,
            'from' => $from, 'to' => $to, 'days' => $days,
            'profit' => $reports->profitByProduct($branch->id, $from->copy()->utc(), $to->copy()->utc()),
            'value' => $reports->stockValue($branch->id),
            'notSelling' => $reports->notSelling($branch->id, $days),
        ]);
    }
}
