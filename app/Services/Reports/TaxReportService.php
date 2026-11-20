<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderTax;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Services\Tax\TaxService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Reports → Tax (Express POS step 9, the `tax` module): the tax charged in
 * a period, per tax line and per day, for filing. Counted on orders
 * placed in the period (the same orders as the Sales report); tax on
 * refunds completed in the period comes off, in proportion to the
 * refunded share of each order.
 */
class TaxReportService
{
    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly TaxService $tax,
    ) {}

    /**
     * @param  ?list<int>  $branchIds
     * @return array{lines: list<array{label: string, inclusive: bool, total: int}>, days: list<array{date: string, orders: int, sales: int, tax: int}>, orders_count: int, taxable: int, tax_total: int, refunded_tax: int, due: int}
     */
    public function build(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);

        $orders = $this->orders($from, $to, $scopeIds)
            ->get(['id', 'placed_at', 'subtotal', 'discount_total', 'delivery_fee', 'tax_total', 'total']);
        $breakdown = $this->tax->breakdown($orders);

        $lines = OrderTax::whereIn('order_id', $this->orders($from, $to, $scopeIds)->select('id'))
            ->selectRaw('name, rate_basis_points, inclusive, sum(food_amount) as total')
            ->groupBy('name', 'rate_basis_points', 'inclusive')
            ->orderByDesc('rate_basis_points')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'label' => TaxService::label($row->name, (int) $row->rate_basis_points),
                'inclusive' => (bool) $row->inclusive,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        // Sales before tax on these orders (only food is taxed).
        $taxable = fn (Order $order) => $this->tax->netFood($order, $breakdown);

        $days = $orders
            ->groupBy(fn (Order $order) => $order->placed_at->clone()->timezone('Africa/Accra')->toDateString())
            ->map(fn ($dayOrders, string $date) => [
                'date' => $date,
                'orders' => $dayOrders->count(),
                'sales' => (int) $dayOrders->sum($taxable),
                'tax' => (int) $dayOrders->sum('tax_total'),
            ])
            ->sortKeys()
            ->values()
            ->all();

        $refundedTax = (int) Refund::where('status', Refund::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->whereHas('order', fn (Builder $q) => $q->withoutGlobalScope(BranchScope::class)->where('tax_total', '>', 0)
                ->when($scopeIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $scopeIds)))
            ->with(['order' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->select('id', 'tax_total', 'total')])
            ->get()
            ->sum(fn (Refund $refund) => $this->tax->refundTax($refund));

        $taxTotal = (int) $orders->sum('tax_total');

        return [
            'lines' => $lines,
            'days' => $days,
            'orders_count' => $orders->count(),
            'taxable' => (int) $orders->sum($taxable),
            'tax_total' => $taxTotal,
            'refunded_tax' => $refundedTax,
            'due' => $taxTotal - $refundedTax,
        ];
    }

    /**
     * @param  ?list<int>  $scopeIds
     */
    private function orders(Carbon $from, Carbon $to, ?array $scopeIds): Builder
    {
        return Order::withoutGlobalScope(BranchScope::class)
            ->where('tax_total', '>', 0)
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->whereNotIn('status', SalesStatementService::NON_SALES_STATUSES)
            ->when($scopeIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $scopeIds));
    }
}
