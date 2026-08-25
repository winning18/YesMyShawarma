<?php

namespace App\Services\Performance;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\VisitorSession;
use App\Services\Reports\OrderReportService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backs the Performance page — owner gets it cross-branch, manager gets
 * their own branch only (dashboard.performance is granted to both;
 * PerformanceController decides $ignoreBranchScope by actual role, never
 * by which branch happens to be selected). Reuses OrderReportService for
 * the actual order aggregation (financialSummary/operationalSummary)
 * rather than re-querying orders here — this class only adds what that
 * service doesn't already do: period-over-period % change, the two-line
 * chart series, item-level sales, and visitor conversion.
 */
class PerformanceReportService
{
    public function __construct(private readonly OrderReportService $orderReports) {}

    /**
     * $ignoreBranchScope defaults true — every existing owner call site
     * relies on that cross-branch aggregation (same reasoning as the old
     * OwnerOverviewController this replaces: it must never be silently
     * narrowed by whatever branch happens to be selected). A manager
     * viewing their own branch is the only caller that passes false.
     */
    /**
     * @param  ?list<int>  $branchIds  general_manager's multi-branch oversight set — see
     *                                 OrderReportService::ordersInRange() for the full reasoning.
     */
    public function salesSummary(Carbon $from, Carbon $to, bool $ignoreBranchScope = true, ?array $branchIds = null): array
    {
        [$previousFrom, $previousTo] = $this->previousPeriod($from, $to);

        $currentFinancial = $this->orderReports->financialSummary($from, $to, $ignoreBranchScope, branchIds: $branchIds);
        $previousFinancial = $this->orderReports->financialSummary($previousFrom, $previousTo, $ignoreBranchScope, branchIds: $branchIds);

        // "Orders received" counts everything placed in the window,
        // including ones later cancelled/rejected — financialSummary's
        // order count only covers revenue-contributing statuses, which
        // undercounts what a manager would call "orders we got today".
        $currentOrders = $this->orderReports->operationalSummary($from, $to, $ignoreBranchScope, branchIds: $branchIds)['total_orders'];
        $previousOrders = $this->orderReports->operationalSummary($previousFrom, $previousTo, $ignoreBranchScope, branchIds: $branchIds)['total_orders'];

        $currentConversion = $this->conversionRate($from, $to);
        $previousConversion = $this->conversionRate($previousFrom, $previousTo);

        return [
            'sales' => [
                'value' => $currentFinancial['revenue_total'],
                'change_pct' => $this->percentChange($currentFinancial['revenue_total'], $previousFinancial['revenue_total']),
            ],
            'orders' => [
                'value' => $currentOrders,
                'change_pct' => $this->percentChange($currentOrders, $previousOrders),
            ],
            'average_order_value' => [
                'value' => $currentFinancial['average_order_value'],
                'change_pct' => $this->percentChange($currentFinancial['average_order_value'], $previousFinancial['average_order_value']),
            ],
            'conversion' => [
                'value' => $currentConversion,
                'change_pct' => $this->percentChange($currentConversion, $previousConversion),
            ],
            'chart' => [
                'labels' => $currentFinancial['revenue_by_day']->keys()->map(fn (string $day) => Carbon::parse($day)->format('d M'))->all(),
                'current' => $currentFinancial['revenue_by_day']->values()->all(),
                'previous' => $previousFinancial['revenue_by_day']->values()->all(),
            ],
        ];
    }

    /**
     * A raw query builder aggregation (grouped SUM), never routed through
     * Eloquent — order_items has no branch_id of its own, and Order's
     * BranchScope only ever applies to Eloquent queries, so unlike
     * salesSummary() (which gets branch scoping for free by simply not
     * ignoring it) this needs an explicit branch_id filter of its own
     * whenever $ignoreBranchScope is false.
     *
     * @param  ?list<int>  $branchIds  general_manager's multi-branch oversight set — takes
     *                                 precedence over $branchId when given, same as OrderReportService.
     * @return LengthAwarePaginator<int, array{menu_item_id: int, name: string, image_url: ?string, amount_sold: int, item_sales: int, sales_share_pct: float}>
     */
    public function itemSales(Carbon $from, Carbon $to, string $sort, string $direction, int $perPage = 10, bool $ignoreBranchScope = true, ?int $branchId = null, ?array $branchIds = null): LengthAwarePaginator
    {
        $sortColumn = in_array($sort, ['amount_sold', 'item_sales'], true) ? $sort : 'amount_sold';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $baseQuery = fn () => DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->whereNotIn('orders.status', Order::NON_REVENUE_STATUSES)
            ->when(! $ignoreBranchScope && $branchIds !== null, fn ($query) => $query->whereIn('orders.branch_id', $branchIds))
            ->when(! $ignoreBranchScope && $branchIds === null, fn ($query) => $query->where('orders.branch_id', $branchId));

        $totalRevenue = (int) $baseQuery()->sum('order_items.line_total');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $baseQuery()
            ->selectRaw('order_items.menu_item_id, SUM(order_items.quantity) as amount_sold, SUM(order_items.line_total) as item_sales')
            ->groupBy('order_items.menu_item_id')
            ->orderBy($sortColumn, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $menuItems = MenuItem::withTrashed()
            ->whereIn('id', $paginator->pluck('menu_item_id'))
            ->get()
            ->keyBy('id');

        $paginator->through(function (object $row) use ($menuItems, $totalRevenue) {
            $item = $menuItems->get($row->menu_item_id);

            return [
                'menu_item_id' => $row->menu_item_id,
                'name' => $item?->name ?? __('Removed item'),
                'image_url' => $item?->imageUrl(),
                'amount_sold' => (int) $row->amount_sold,
                'item_sales' => (int) $row->item_sales,
                'sales_share_pct' => $totalRevenue > 0 ? round($row->item_sales / $totalRevenue * 100, 1) : 0.0,
            ];
        });

        return $paginator;
    }

    /**
     * Of the visitor sessions first seen in this window, what share ever
     * placed an order (whereNotNull('order_id'), set no matter which day
     * within — or after — the window the order itself landed on). Null
     * with zero visits rather than 0% — no data is a different message
     * than "everyone bounced".
     */
    private function conversionRate(Carbon $from, Carbon $to): ?float
    {
        $visits = VisitorSession::whereBetween('created_at', [$from, $to])->count();

        if ($visits === 0) {
            return null;
        }

        $conversions = VisitorSession::whereBetween('created_at', [$from, $to])->whereNotNull('order_id')->count();

        return round($conversions / $visits * 100, 1);
    }

    /**
     * The equivalent-length window immediately before $from — e.g. "last
     * 7 days" compares against the 7 days before that, so a change_pct is
     * always like-for-like in number of days.
     */
    private function previousPeriod(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->clone()->subSecond();
        $previousFrom = $previousTo->clone()->subDays($days)->addSecond();

        return [$previousFrom, $previousTo];
    }

    private function percentChange(int|float|null $current, int|float|null $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
