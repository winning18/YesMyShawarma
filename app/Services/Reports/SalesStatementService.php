<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Models\ShiftExpense;
use App\Services\Branches\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The downloadable sales statement on the Invoices and sales tab,
 * modelled on Bolt Food's weekly partner sales report: one row per
 * order, grouped by day with a TOTAL row per day, then expenses and a
 * deductions block ending in the period's net figure. Every format
 * (PDF/XLSX/CSV, see SalesStatementExporter) renders this one array, so
 * the three can never disagree on a number.
 *
 * Two report types, never mixed:
 *  - 'sales'     — every order except Bolt Food. Sales per order is
 *                  subtotal − discount; the delivery fee is listed but
 *                  never counted as sales, because it goes to the rider.
 *                  Completed refunds and shift expenses are deducted.
 *  - 'bolt_food' — Bolt Food orders only. No refunds, expenses or
 *                  delivery: Bolt's own statement carries their fees.
 *
 * Refunds are deducted in the period they were *completed* (when the
 * money actually left), regardless of when the order was placed. A
 * refund never changes orders.status (see RefundService), so a refunded
 * order's sale is still in the order table and the refund line is the
 * only deduction — nothing is counted twice.
 *
 * Only 'paid'-onwards orders count as sales: besides
 * Order::NON_REVENUE_STATUSES this also leaves out 'failed' (payment
 * never went through) and 'pending_payment' (still waiting on Paystack).
 * The excluded statuses the business asked to see are counted, not
 * listed, in 'excluded_counts'.
 *
 * Branch arguments follow WeeklySalesReportService's convention. The
 * header shows the one branch being reported on, or Ga Odumase (the main
 * branch) for a general all-branches report.
 */
class SalesStatementService
{
    public const TYPE_SALES = 'sales';

    public const TYPE_BOLT_FOOD = 'bolt_food';

    public const TYPES = [self::TYPE_SALES, self::TYPE_BOLT_FOOD];

    public const MAIN_BRANCH_SLUG = 'ga-odumase';

    /**
     * Shown as counts under the totals, in this order.
     *
     * @var list<string>
     */
    public const EXCLUDED_STATUSES = ['cancelled', 'rejected', 'abandoned', 'refunded', 'failed'];

    /**
     * Every status that is never a sale: EXCLUDED_STATUSES plus
     * 'pending_payment' — an order still waiting on Paystack hasn't
     * failed, it just isn't paid, so it's left out of the counts too.
     * Shared with WeeklySalesReportService so the history table and this
     * statement agree.
     *
     * @var list<string>
     */
    public const NON_SALES_STATUSES = ['cancelled', 'rejected', 'abandoned', 'refunded', 'failed', 'pending_payment'];

    private const PAYMENT_LABELS = [
        'cash' => 'Cash',
        'momo' => 'MoMo',
        'paystack' => 'Paystack',
        'bolt_food' => 'Bolt Food',
    ];

    public function __construct(private readonly BranchContext $context) {}

    /**
     * @param  ?list<int>  $branchIds
     * @return array<string, mixed>
     */
    public function build(string $type, Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $scopeIds = $this->resolveBranchIds($ignoreBranchScope, $branchId, $branchIds);
        $singleBranchId = $branchId ?? ($scopeIds !== null && count($scopeIds) === 1 && ! $ignoreBranchScope && $branchIds === null ? $scopeIds[0] : null);
        $general = $singleBranchId === null;
        $isSales = $type === self::TYPE_SALES;

        $columns = $this->columns($type, $general);
        $moneyKeys = collect($columns)->where('money', true)->pluck('key')->all();

        $orders = $this->ordersQuery($type, $from, $to, $scopeIds)
            ->whereNotIn('status', self::NON_SALES_STATUSES)
            ->with('branch:id,name')
            ->orderBy('placed_at')
            ->orderBy('id')
            ->get();

        $number = 0;
        $days = $orders
            ->groupBy(fn (Order $order) => $order->placed_at->clone()->timezone('Africa/Accra')->toDateString())
            ->map(function (Collection $dayOrders, string $date) use (&$number, $moneyKeys) {
                $rows = [];
                foreach ($dayOrders as $order) {
                    $rows[] = $this->row($order, ++$number);
                }

                return [
                    'date' => $date,
                    'rows' => $rows,
                    'totals' => $this->sumKeys($rows, $moneyKeys),
                ];
            })
            ->values()
            ->all();

        $allRows = collect($days)->flatMap(fn (array $day) => $day['rows'])->all();
        $totals = $this->sumKeys($allRows, $moneyKeys);

        $expenses = $isSales ? $this->expenses($from, $to, $scopeIds) : [];
        $expensesTotal = (int) collect($expenses)->sum('amount');
        $refunds = $isSales ? $this->refunds($from, $to, $scopeIds) : ['total' => 0, 'count' => 0];

        $salesTotal = $totals[$isSales ? 'sales' : 'total'];
        $net = $salesTotal - $refunds['total'] - $expensesTotal;

        $summaryLines = $isSales
            ? [
                ['label' => 'Total sales (after discounts)', 'amount' => $salesTotal, 'emphasis' => false],
                ['label' => 'Refund deductions', 'amount' => -$refunds['total'], 'emphasis' => false],
                ['label' => 'Expenses', 'amount' => -$expensesTotal, 'emphasis' => false],
                ['label' => 'Net sales', 'amount' => $net, 'emphasis' => true],
            ]
            : [
                ['label' => 'Total Bolt Food sales', 'amount' => $salesTotal, 'emphasis' => true],
            ];

        $memoLines = $isSales
            ? [
                ['label' => 'Orders', 'value' => (string) count($allRows)],
                ['label' => 'Discounts given', 'value' => $this->money($totals['discount'])],
                ['label' => 'Delivery fees passed to riders', 'value' => $this->money($totals['delivery_fee'])],
                ['label' => 'Refunds completed', 'value' => (string) $refunds['count']],
            ]
            : [
                ['label' => 'Orders', 'value' => (string) count($allRows)],
                ['label' => 'Discounts given', 'value' => $this->money($totals['discount'])],
            ];

        return [
            'type' => $type,
            'title' => $isSales ? 'Sales report' : 'Bolt Food report',
            'from' => $from,
            'to' => $to,
            'branch' => $this->headerBranch($singleBranchId),
            'general' => $general,
            'columns' => $columns,
            'days' => $days,
            'totals' => $totals,
            'orders_count' => count($allRows),
            'sales_total' => $salesTotal,
            'refunds_total' => $refunds['total'],
            'refunds_count' => $refunds['count'],
            'expenses' => $expenses,
            'expenses_total' => $expensesTotal,
            'net_total' => $net,
            'summary_lines' => $summaryLines,
            'memo_lines' => $memoLines,
            'excluded_counts' => $this->excludedCounts($type, $from, $to, $scopeIds),
        ];
    }

    /**
     * @return list<array{key: string, label: string, money: bool}>
     */
    private function columns(string $type, bool $general): array
    {
        $columns = [
            ['key' => 'no', 'label' => 'No', 'money' => false],
            ['key' => 'date', 'label' => 'Date', 'money' => false],
            ['key' => 'time', 'label' => 'Time', 'money' => false],
            ['key' => 'reference', 'label' => 'Order ID', 'money' => false],
        ];

        if ($general) {
            $columns[] = ['key' => 'branch', 'label' => 'Branch', 'money' => false];
        }

        if ($type === self::TYPE_BOLT_FOOD) {
            return [
                ...$columns,
                ['key' => 'subtotal', 'label' => 'Subtotal', 'money' => true],
                ['key' => 'discount', 'label' => 'Discount', 'money' => true],
                ['key' => 'total', 'label' => 'Total', 'money' => true],
            ];
        }

        return [
            ...$columns,
            ['key' => 'type', 'label' => 'Type', 'money' => false],
            ['key' => 'payment', 'label' => 'Payment', 'money' => false],
            ['key' => 'subtotal', 'label' => 'Subtotal', 'money' => true],
            ['key' => 'discount', 'label' => 'Discount', 'money' => true],
            ['key' => 'sales', 'label' => 'Sales', 'money' => true],
            ['key' => 'delivery_fee', 'label' => 'Delivery fee (rider)', 'money' => true],
            ['key' => 'total', 'label' => 'Customer paid', 'money' => true],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function row(Order $order, int $number): array
    {
        $placedAt = $order->placed_at->clone()->timezone('Africa/Accra');

        return [
            'no' => $number,
            'date' => $placedAt->toDateString(),
            'time' => $placedAt->format('H:i'),
            'reference' => $order->reference,
            'branch' => $order->branch?->name ?? '',
            'type' => Str::headline($order->fulfilment_type),
            'payment' => self::PAYMENT_LABELS[$order->payment_method] ?? Str::headline($order->payment_method),
            'subtotal' => (int) $order->subtotal,
            'discount' => (int) $order->discount_total,
            'sales' => (int) $order->subtotal - (int) $order->discount_total,
            'delivery_fee' => (int) $order->delivery_fee,
            'total' => (int) $order->total,
        ];
    }

    /**
     * @param  list<array<string, int|string>>  $rows
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function sumKeys(array $rows, array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => (int) collect($rows)->sum($key)])
            ->all();
    }

    /**
     * @param  ?list<int>  $scopeIds
     */
    private function ordersQuery(string $type, Carbon $from, Carbon $to, ?array $scopeIds): Builder
    {
        $query = Order::query()
            ->withoutGlobalScope(BranchScope::class)
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$from->clone()->utc(), $to->clone()->utc()]);

        if ($scopeIds !== null) {
            $query->whereIn('branch_id', $scopeIds);
        }

        return $type === self::TYPE_BOLT_FOOD
            ? $query->whereIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS)
            : $query->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS);
    }

    /**
     * @param  ?list<int>  $scopeIds
     * @return array<string, int>
     */
    private function excludedCounts(string $type, Carbon $from, Carbon $to, ?array $scopeIds): array
    {
        $counts = $this->ordersQuery($type, $from, $to, $scopeIds)
            ->whereIn('status', self::EXCLUDED_STATUSES)
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(self::EXCLUDED_STATUSES)
            ->mapWithKeys(fn (string $status) => [Str::headline($status) => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    /**
     * @param  ?list<int>  $scopeIds
     * @return list<array{date: string, time: string, branch: string, description: string, amount: int}>
     */
    private function expenses(Carbon $from, Carbon $to, ?array $scopeIds): array
    {
        return ShiftExpense::query()
            ->whereBetween('created_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->whereHas('shift', fn (Builder $shift) => $scopeIds === null ? $shift : $shift->whereIn('branch_id', $scopeIds))
            ->with('shift.branch:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (ShiftExpense $expense) {
                $at = $expense->created_at->clone()->timezone('Africa/Accra');

                return [
                    'date' => $at->toDateString(),
                    'time' => $at->format('H:i'),
                    'branch' => $expense->shift?->branch?->name ?? '',
                    'description' => $expense->description,
                    'amount' => (int) $expense->amount,
                ];
            })
            ->all();
    }

    /**
     * @param  ?list<int>  $scopeIds
     * @return array{total: int, count: int}
     */
    private function refunds(Carbon $from, Carbon $to, ?array $scopeIds): array
    {
        $query = Refund::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('status', Refund::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->whereHas('order', fn (Builder $order) => $order
                ->withoutGlobalScope(BranchScope::class)
                ->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS));

        if ($scopeIds !== null) {
            $query->whereIn('branch_id', $scopeIds);
        }

        return [
            'total' => (int) (clone $query)->sum('amount'),
            'count' => (clone $query)->count(),
        ];
    }

    /**
     * Null means every branch. The ambient case (no explicit filter and
     * not ignoring scope) pins to BranchContext's current branch, the
     * same branch BranchScope would have applied — resolved here once
     * because shift expenses have no BranchScope of their own.
     *
     * @param  ?list<int>  $branchIds
     * @return ?list<int>
     */
    private function resolveBranchIds(bool $ignoreBranchScope, ?int $branchId, ?array $branchIds): ?array
    {
        if ($branchId !== null) {
            return [$branchId];
        }

        if ($branchIds !== null) {
            return array_values($branchIds);
        }

        if ($ignoreBranchScope) {
            return null;
        }

        $current = $this->context->id();

        return $current !== null ? [$current] : null;
    }

    private function headerBranch(?int $singleBranchId): ?Branch
    {
        if ($singleBranchId !== null) {
            return Branch::find($singleBranchId);
        }

        return Branch::where('slug', self::MAIN_BRANCH_SLUG)->first() ?? Branch::orderBy('id')->first();
    }

    private function money(int $pesewas): string
    {
        return 'GHS '.number_format($pesewas / 100, 2);
    }
}
