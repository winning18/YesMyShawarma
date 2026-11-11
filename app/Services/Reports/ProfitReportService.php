<?php

namespace App\Services\Reports;

use App\Models\MarketList;
use App\Models\MarketListItem;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Profit tab (owner, 2026-11-11): sales minus approved market-list
 * purchases and expenses, per day, week, month or year, plus what was
 * spent on each kind (all the chicken, all the transport…).
 *
 *  - Sales: exactly SalesStatementService's 'sales' figure — every order
 *    except Bolt Food, subtotal − discount (the delivery fee goes to the
 *    rider), paid-onwards statuses only, by placed_at.
 *  - Refunds: completed refunds, by completed_at, same as the statement.
 *  - Purchases: approved market lists, by purchase date. Pending and
 *    sent-back lists are reported separately and never counted.
 *  - Bolt Food is its own line, added only into "profit incl. Bolt Food"
 *    and labelled before Bolt's commission, which the app doesn't know.
 *
 * "Profit" is only as complete as what gets recorded: rent, salaries or
 * utilities count only if someone puts them on the market list.
 *
 * Branch arguments follow WeeklySalesReportService's convention, resolved
 * by ReportBranchResolver.
 */
class ProfitReportService
{
    public const GROUPINGS = ['day', 'week', 'month', 'year'];

    public function __construct(private readonly ReportBranchResolver $branches) {}

    /**
     * @param  ?list<int>  $branchIds
     * @return array<string, mixed>
     */
    public function build(Carbon $from, Carbon $to, string $groupBy, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);
        $singleBranchId = $this->branches->singleBranchId($ignoreBranchScope, $branchId, $branchIds);
        $periodOf = fn (Carbon $date) => $this->periodKey($date, $groupBy);

        $sales = $this->salesByPeriod($from, $to, $scopeIds, false, $periodOf);
        $bolt = $this->salesByPeriod($from, $to, $scopeIds, true, $periodOf);
        $refunds = $this->refundsByPeriod($from, $to, $scopeIds, $periodOf);
        $purchases = $this->purchasesByPeriod($from, $to, $scopeIds, $periodOf);

        $periods = collect($this->periodKeys($from, $to, $groupBy))
            ->map(function (string $key) use ($sales, $bolt, $refunds, $purchases, $groupBy) {
                $profit = ($sales[$key] ?? 0) - ($refunds[$key] ?? 0) - ($purchases[$key] ?? 0);

                return [
                    'key' => $key,
                    'label' => $this->periodLabel($key, $groupBy),
                    'sales' => $sales[$key] ?? 0,
                    'refunds' => $refunds[$key] ?? 0,
                    'purchases' => $purchases[$key] ?? 0,
                    'profit' => $profit,
                    'bolt_food' => $bolt[$key] ?? 0,
                    'profit_with_bolt' => $profit + ($bolt[$key] ?? 0),
                ];
            })
            ->values();

        $totals = collect(['sales', 'refunds', 'purchases', 'profit', 'bolt_food', 'profit_with_bolt'])
            ->mapWithKeys(fn (string $column) => [$column => (int) $periods->sum($column)])
            ->all();

        return [
            'title' => 'Profit report',
            'from' => $from,
            'to' => $to,
            'group_by' => $groupBy,
            'branch' => $this->branches->headerBranch($singleBranchId),
            'general' => $singleBranchId === null,
            'periods' => $periods->all(),
            'totals' => $totals,
            'by_kind' => $this->byKind($from, $to, $scopeIds),
            'awaiting' => $this->awaiting($from, $to, $scopeIds),
        ];
    }

    /**
     * Every kind bought in the period, biggest spend first. A quantity
     * total is only given when every item of that kind used the same unit
     * — adding kg to crates would be meaningless.
     *
     * @param  ?list<int>  $scopeIds
     * @return list<array{kind: string, count: int, total: int, quantity: ?float, unit: ?string}>
     */
    private function byKind(Carbon $from, Carbon $to, ?array $scopeIds): array
    {
        return $this->approvedItems($from, $to, $scopeIds)
            ->with('category:id,name')
            ->get(['id', 'expense_category_id', 'amount', 'quantity', 'unit'])
            ->groupBy('expense_category_id')
            ->map(function (Collection $items) {
                $units = $items->map(fn (MarketListItem $item) => $item->quantity === null ? null : mb_strtolower(trim((string) $item->unit)))->unique();
                $sameUnit = $units->count() === 1 && $units->first() !== null;

                return [
                    'kind' => $items->first()->category?->name ?? '',
                    'count' => $items->count(),
                    'total' => (int) $items->sum('amount'),
                    'quantity' => $sameUnit ? round((float) $items->sum(fn (MarketListItem $item) => (float) $item->quantity), 2) : null,
                    'unit' => $sameUnit ? $items->first()->unit : null,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Lists in the period not yet approved — shown, never counted.
     *
     * @param  ?list<int>  $scopeIds
     * @return array{count: int, total: int}
     */
    private function awaiting(Carbon $from, Carbon $to, ?array $scopeIds): array
    {
        $query = $this->lists($from, $to, $scopeIds)->whereIn('status', [MarketList::STATUS_PENDING, MarketList::STATUS_NEEDS_CHANGES]);

        return ['count' => (clone $query)->count(), 'total' => (int) (clone $query)->sum('total')];
    }

    /**
     * @param  ?list<int>  $scopeIds
     * @return array<string, int>
     */
    private function salesByPeriod(Carbon $from, Carbon $to, ?array $scopeIds, bool $boltFood, callable $periodOf): array
    {
        $totals = [];

        Order::query()
            ->withoutGlobalScope(BranchScope::class)
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->whereNotIn('status', SalesStatementService::NON_SALES_STATUSES)
            ->when($boltFood,
                fn (Builder $query) => $query->whereIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS),
                fn (Builder $query) => $query->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS))
            ->when($scopeIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $scopeIds))
            ->toBase()
            ->select(['placed_at', 'subtotal', 'discount_total'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $order) use (&$totals, $periodOf) {
                $key = $periodOf(Carbon::parse($order->placed_at, 'UTC')->timezone('Africa/Accra'));
                $totals[$key] = ($totals[$key] ?? 0) + (int) $order->subtotal - (int) $order->discount_total;
            });

        return $totals;
    }

    /**
     * @param  ?list<int>  $scopeIds
     * @return array<string, int>
     */
    private function refundsByPeriod(Carbon $from, Carbon $to, ?array $scopeIds, callable $periodOf): array
    {
        return Refund::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('status', Refund::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->whereHas('order', fn (Builder $order) => $order
                ->withoutGlobalScope(BranchScope::class)
                ->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS))
            ->when($scopeIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $scopeIds))
            ->get(['completed_at', 'amount'])
            ->groupBy(fn (Refund $refund) => $periodOf($refund->completed_at->clone()->timezone('Africa/Accra')))
            ->map(fn (Collection $refunds) => (int) $refunds->sum('amount'))
            ->all();
    }

    /**
     * @param  ?list<int>  $scopeIds
     * @return array<string, int>
     */
    private function purchasesByPeriod(Carbon $from, Carbon $to, ?array $scopeIds, callable $periodOf): array
    {
        return $this->lists($from, $to, $scopeIds)
            ->where('status', MarketList::STATUS_APPROVED)
            ->get(['purchased_on', 'total'])
            ->groupBy(fn (MarketList $list) => $periodOf(Carbon::parse($list->purchased_on->toDateString(), 'Africa/Accra')))
            ->map(fn (Collection $lists) => (int) $lists->sum('total'))
            ->all();
    }

    /**
     * @param  ?list<int>  $scopeIds
     */
    private function lists(Carbon $from, Carbon $to, ?array $scopeIds): Builder
    {
        return MarketList::query()
            ->withoutGlobalScope(BranchScope::class)
            ->whereBetween('purchased_on', [$from->toDateString(), $to->toDateString()])
            ->when($scopeIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $scopeIds));
    }

    /**
     * @param  ?list<int>  $scopeIds
     */
    private function approvedItems(Carbon $from, Carbon $to, ?array $scopeIds): Builder
    {
        return MarketListItem::query()->whereHas('marketList', fn (Builder $list) => $list
            ->withoutGlobalScope(BranchScope::class)
            ->where('status', MarketList::STATUS_APPROVED)
            ->whereBetween('purchased_on', [$from->toDateString(), $to->toDateString()])
            ->when($scopeIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $scopeIds)));
    }

    private function periodKey(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->clone()->startOfWeek()->toDateString(),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->toDateString(),
        };
    }

    /**
     * Every period in the range, in order, including empty ones — a day
     * with no sales and no purchases still shows as a zero row.
     *
     * @return list<string>
     */
    private function periodKeys(Carbon $from, Carbon $to, string $groupBy): array
    {
        $keys = [];
        $cursor = $from->clone()->startOfDay();

        while ($cursor->lte($to)) {
            $keys[$this->periodKey($cursor, $groupBy)] = true;
            $cursor->addDay();
        }

        return array_keys($keys);
    }

    private function periodLabel(string $key, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'Week of '.Carbon::parse($key)->format('d M Y'),
            'month' => Carbon::createFromFormat('Y-m-d', $key.'-01')->format('F Y'),
            'year' => $key,
            default => Carbon::parse($key)->format('D d M Y'),
        };
    }
}
