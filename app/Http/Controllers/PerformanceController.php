<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Services\Performance\PerformanceReportService;
use App\Services\Reports\OrderReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The whole point of this page is cross-branch comparison, so — like the
 * Overview page it replaces — it always aggregates every branch rather
 * than respecting the branch switcher.
 */
class PerformanceController extends Controller
{
    /**
     * @var array<string, int>
     */
    private const RANGE_DAYS = ['today' => 1, '7' => 7, '30' => 30];

    public function index(Request $request, PerformanceReportService $performance, OrderReportService $reports): View
    {
        Gate::authorize('dashboard.performance');

        $validated = $request->validate([
            'tab' => ['nullable', 'in:sales,operations'],
            'range' => ['nullable', 'in:today,7,30'],
            'sort' => ['nullable', 'in:amount_sold,item_sales'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $tab = $validated['tab'] ?? 'sales';
        $rangeKey = $validated['range'] ?? '7';
        $days = self::RANGE_DAYS[$rangeKey];

        $to = Carbon::now('Africa/Accra')->endOfDay();
        $from = $to->clone()->subDays($days - 1)->startOfDay();

        $data = [
            'tab' => $tab,
            'rangeKey' => $rangeKey,
            'from' => $from,
            'to' => $to,
        ];

        if ($tab === 'sales') {
            $sort = $validated['sort'] ?? 'amount_sold';
            $dir = $validated['dir'] ?? 'desc';

            $data['summary'] = $performance->salesSummary($from->clone()->utc(), $to->clone()->utc());
            $data['itemSales'] = $performance->itemSales($from->clone()->utc(), $to->clone()->utc(), $sort, $dir);
            $data['sort'] = $sort;
            $data['dir'] = $dir;
        } else {
            $data['operational'] = $reports->operationalSummary($from->clone()->utc(), $to->clone()->utc(), ignoreBranchScope: true);
            $data['branches'] = $this->branchBreakdown($from->clone()->utc(), $to->clone()->utc());
        }

        return view('dashboard.performance.index', $data);
    }

    /**
     * @return Collection<int, array{branch: Branch, orders: int, revenue: int, escalated: int}>
     */
    private function branchBreakdown(Carbon $from, Carbon $to): Collection
    {
        $orders = Order::withoutGlobalScope(BranchScope::class)
            ->whereBetween('placed_at', [$from, $to])
            ->get(['id', 'branch_id', 'status', 'total']);

        $escalatedCounts = Order::withoutGlobalScope(BranchScope::class)
            ->whereBetween('placed_at', [$from, $to])
            ->whereHas('events', fn ($q) => $q->whereNotNull('meta->escalation_level'))
            ->get(['id', 'branch_id'])
            ->countBy('branch_id');

        return Branch::orderBy('name')->get()->map(function (Branch $branch) use ($orders, $escalatedCounts) {
            $branchOrders = $orders->where('branch_id', $branch->id);
            $revenue = $branchOrders->whereNotIn('status', Order::NON_REVENUE_STATUSES)->sum('total');

            return [
                'branch' => $branch,
                'orders' => $branchOrders->count(),
                'revenue' => $revenue,
                'escalated' => $escalatedCounts->get($branch->id, 0),
            ];
        });
    }
}
