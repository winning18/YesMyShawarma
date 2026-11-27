<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockCount;
use App\Services\Branches\BranchContext;
use App\Services\Stock\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Stock → Stock counts (R4, 2026-11-27). Pharmacies and shops; stock.manage. StockCountService does the work. */
class StockCountController extends Controller
{
    public function index(BranchContext $context): View|RedirectResponse
    {
        Gate::authorize('stock.manage');
        if (! $branch = $context->branch()) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to see its stock.'));
        }

        return view('dashboard.inventory.counts.index', [
            'branch' => $branch,
            'counts' => StockCount::where('branch_id', $branch->id)->with(['starter:id,name', 'finisher:id,name'])->withCount('items')->latest('id')->paginate(30),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, BranchContext $context, StockCountService $counts): RedirectResponse
    {
        Gate::authorize('stock.manage');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');
        $v = $request->validate(['category_id' => ['nullable', 'integer', 'exists:categories,id'], 'note' => ['nullable', 'string', 'max:255']]);

        $count = $counts->start($branch->id, $request->user(), $v['category_id'] ?? null, $v['note'] ?? null);

        return redirect()->route('dashboard.inventory.counts.show', $count);
    }

    public function show(StockCount $count): View
    {
        Gate::authorize('stock.manage');

        return view('dashboard.inventory.counts.show', ['count' => $count, 'lines' => $count->items()->with('stockItem')->get()->sortBy(fn ($l) => $l->stockItem->name)->values()]);
    }

    public function update(Request $request, StockCount $count, StockCountService $counts): RedirectResponse
    {
        Gate::authorize('stock.manage');
        $counts->save($count, (array) $request->input('counted', []));

        if ($request->boolean('finish')) {
            $corrected = $counts->finish($count, $request->user());

            return redirect()->route('dashboard.inventory.counts.index')->with('status', trans_choice('{0} Count finished: everything matched.|{1} Count finished: 1 product corrected.|[2,*] Count finished: :count products corrected.', $corrected));
        }

        return back()->with('status', __('Saved. Carry on counting, then press Finish.'));
    }

    public function cancel(StockCount $count, StockCountService $counts): RedirectResponse
    {
        Gate::authorize('stock.manage');
        $counts->cancel($count);

        return redirect()->route('dashboard.inventory.counts.index')->with('status', __('Count cancelled: nothing changed.'));
    }
}
