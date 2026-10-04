<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Weekly sales aggregation for the Invoices and sales / Weekly report
 * tabs.
 *
 * $branchId/$branchIds/$ignoreBranchScope follow the exact same
 * convention as OrderReportService — null branchId/branchIds and
 * ignoreBranchScope false (every caller's default) leaves BranchScope's
 * own ambient-session filtering in place, unchanged from before these
 * existed. An owner/general_manager explicitly picking "all branches" or
 * one specific branch via these pages' own filter passes them instead of
 * relying on whatever happens to be the session's current branch.
 */
class WeeklySalesReportService
{
    /**
     * @param  ?list<int>  $branchIds
     * @return array{start: Carbon, end: Carbon, city: string, currency: string, orders_count: int, total: int}
     */
    public function summary(Carbon $weekStart, Carbon $weekEnd, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $orders = $this->ordersInRange($weekStart, $weekEnd, $ignoreBranchScope, $branchId, $branchIds)
            ->whereNotIn('status', Order::NON_REVENUE_STATUSES)
            ->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS)
            ->get(['id', 'total']);

        return [
            'start' => $weekStart,
            'end' => $weekEnd,
            'city' => 'Accra',
            'currency' => 'GHS',
            'orders_count' => $orders->count(),
            'total' => (int) $orders->sum('total'),
        ];
    }

    /**
     * One row per ISO week that has at least one revenue-contributing
     * order, most recent first. A week with zero qualifying orders is
     * left out rather than synthesised as an empty row — for an
     * operating restaurant that's expected to be a rare gap.
     *
     * Bucketed in PHP rather than a DB-side YEARWEEK()/date-trunc
     * grouping — this app's test suite runs on SQLite while production
     * runs MySQL, and this keeps the query portable between the two
     * rather than relying on a MySQL-only function. Order volume for a
     * single restaurant business makes pulling every revenue order's id/
     * placed_at/total in one query and grouping it in memory perfectly
     * fine; this is not a high-cardinality table.
     *
     * @param  ?list<int>  $branchIds
     * @return Collection<int, array{start: Carbon, end: Carbon, city: string, currency: string, orders_count: int, total: int}>
     */
    public function weeklyHistory(bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): Collection
    {
        return $this->scopedQuery($ignoreBranchScope, $branchId, $branchIds)
            ->whereNotIn('status', Order::NON_REVENUE_STATUSES)
            ->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS)
            ->get(['id', 'placed_at', 'total'])
            ->groupBy(fn (Order $order) => $order->placed_at->clone()->timezone('Africa/Accra')->startOfWeek()->toDateString())
            ->map(function (Collection $orders, string $weekStartDate) {
                $weekStart = Carbon::parse($weekStartDate, 'Africa/Accra')->startOfWeek();

                return [
                    'start' => $weekStart,
                    'end' => $weekStart->clone()->endOfWeek(),
                    'city' => 'Accra',
                    'currency' => 'GHS',
                    'orders_count' => $orders->count(),
                    'total' => (int) $orders->sum('total'),
                ];
            })
            ->sortByDesc(fn (array $week) => $week['start'])
            ->values();
    }

    /**
     * Individual orders in the week, for the Weekly report tab's raw
     * transaction-level CSV export — distinct from summary()'s single
     * aggregated row.
     *
     * @param  ?list<int>  $branchIds
     * @return Collection<int, Order>
     */
    public function detailedOrders(Carbon $weekStart, Carbon $weekEnd, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): Collection
    {
        return $this->ordersInRange($weekStart, $weekEnd, $ignoreBranchScope, $branchId, $branchIds)
            ->with('customer')
            ->orderBy('placed_at')
            ->get();
    }

    /**
     * @param  ?list<int>  $branchIds
     */
    private function ordersInRange(Carbon $weekStart, Carbon $weekEnd, bool $ignoreBranchScope, ?int $branchId, ?array $branchIds): Builder
    {
        return $this->scopedQuery($ignoreBranchScope, $branchId, $branchIds)
            ->whereBetween('placed_at', [$weekStart->clone()->utc(), $weekEnd->clone()->utc()]);
    }

    /**
     * @param  ?list<int>  $branchIds
     */
    private function scopedQuery(bool $ignoreBranchScope, ?int $branchId, ?array $branchIds): Builder
    {
        // weeklyHistory() pulls all-time data with no placed_at range of
        // its own (unlike summary()/detailedOrders(), whose whereBetween()
        // already excludes a null placed_at for free) — an order that
        // never actually got placed (still pending_payment, placed_at
        // never stamped) isn't a sale to bucket into any week at all, and
        // groupBy()'s own ->clone() on it would crash outright otherwise.
        $query = Order::query()->whereNotNull('placed_at');

        if ($ignoreBranchScope) {
            $query->withoutGlobalScope(BranchScope::class);
        } elseif ($branchIds !== null) {
            $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $branchIds);
        } elseif ($branchId !== null) {
            $query->withoutGlobalScope(BranchScope::class)->where('branch_id', $branchId);
        }

        return $query;
    }
}
