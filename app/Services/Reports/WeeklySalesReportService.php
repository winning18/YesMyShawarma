<?php

namespace App\Services\Reports;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Weekly sales aggregation for the Invoices and sales / Weekly report
 * tabs.
 *
 * Branch-scoped like the rest of the Reports section (ReportsController),
 * not cross-branch like the owner-only Performance page — every query
 * here is a plain Order::query() call, so BranchScope applies exactly as
 * it does anywhere else.
 */
class WeeklySalesReportService
{
    /**
     * @return array{start: Carbon, end: Carbon, city: string, currency: string, orders_count: int, total: int}
     */
    public function summary(Carbon $weekStart, Carbon $weekEnd): array
    {
        $orders = Order::whereBetween('placed_at', [$weekStart->clone()->utc(), $weekEnd->clone()->utc()])
            ->whereNotIn('status', Order::NON_REVENUE_STATUSES)
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
     * @return Collection<int, array{start: Carbon, end: Carbon, city: string, currency: string, orders_count: int, total: int}>
     */
    public function weeklyHistory(): Collection
    {
        return Order::query()
            ->whereNotIn('status', Order::NON_REVENUE_STATUSES)
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
     * @return Collection<int, Order>
     */
    public function detailedOrders(Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return Order::with('customer')
            ->whereBetween('placed_at', [$weekStart->clone()->utc(), $weekEnd->clone()->utc()])
            ->orderBy('placed_at')
            ->get();
    }
}
