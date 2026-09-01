<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PromotionRedemption;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads only — never touches order state. $from/$to are Africa/Accra day
 * boundaries already converted to UTC by the caller (ReportsController),
 * since orders.placed_at is stored UTC per schema.md.
 *
 * $ignoreBranchScope defaults to false, matching the Reports page's own
 * need to respect the caller's currently-selected branch — the
 * owner-only, always-cross-branch Performance page is the one caller
 * that passes true (same reasoning as BranchScope's own docblock: the
 * owner's cross-branch view must never be silently narrowed by whatever
 * branch happens to be left in session).
 */
class OrderReportService
{
    /**
     * @param  ?list<int>  $branchIds  general_manager's multi-branch oversight set — takes
     *                                 precedence over $branchId when given. See ordersInRange().
     */
    public function operationalSummary(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $orders = $this->ordersInRange($from, $to, $ignoreBranchScope, $branchId, $branchIds);
        $orderIds = $orders->pluck('id');

        // order_events, not the denormalised orders.accepted_at alone, is
        // needed here — there is no paid_at column (schema.md), so "time to
        // accept" can only be measured against the paid transition's event.
        $paidEvents = OrderEvent::whereIn('order_id', $orderIds)
            ->where('to_status', 'paid')
            ->get(['order_id', 'created_at'])
            ->keyBy('order_id');

        $escalations = OrderEvent::whereIn('order_id', $orderIds)
            ->whereNotNull('meta->escalation_level')
            ->count();

        $timeToAccept = $orders
            ->filter(fn (Order $o) => $o->accepted_at && $paidEvents->has($o->id))
            ->map(fn (Order $o) => $paidEvents[$o->id]->created_at->diffInSeconds($o->accepted_at))
            ->avg();

        $prepTime = $orders
            ->filter(fn (Order $o) => $o->accepted_at && $o->ready_at)
            ->map(fn (Order $o) => $o->accepted_at->diffInSeconds($o->ready_at))
            ->avg();

        $deliveryTime = $orders
            ->filter(fn (Order $o) => $o->fulfilment_type === 'delivery' && $o->dispatched_at && $o->delivered_at)
            ->map(fn (Order $o) => $o->dispatched_at->diffInSeconds($o->delivered_at))
            ->avg();

        return [
            'total_orders' => $orders->count(),
            'orders_by_day' => $this->fillDays($from, $to, $this->groupByAccraDay($orders)->map->count()),
            'status_breakdown' => $orders->countBy('status'),
            'orders_by_channel' => $orders->countBy('channel'),
            'avg_time_to_accept_minutes' => $this->toMinutes($timeToAccept),
            'avg_prep_time_minutes' => $this->toMinutes($prepTime),
            'avg_delivery_time_minutes' => $this->toMinutes($deliveryTime),
            'escalations' => $escalations,
        ];
    }

    /**
     * revenue_total/revenue_by_day are *net* of refunds — a refund is
     * subtracted from the day it was actually completed
     * (refunds.completed_at), never retroactively rewriting the day the
     * original order was placed. An order placed Monday and refunded
     * Wednesday still counts as Monday's gross sale; Wednesday's net
     * figure is what drops by the refund amount. This is deliberately
     * NOT done by excluding the order via its own status (RefundService
     * never sets orders.status to 'refunded' — see its class docblock)
     * — refunds are read from the refunds table, an independent ledger.
     *
     * revenue_by_payment_method/revenue_by_channel stay gross-only — a
     * deliberate simplification, not an oversight, since per-channel/
     * per-method net figures aren't what shift close or the Performance
     * headline number need.
     *
     * @param  ?list<int>  $branchIds  See operationalSummary().
     */
    public function financialSummary(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $orders = $this->ordersInRange($from, $to, $ignoreBranchScope, $branchId, $branchIds);
        $revenueOrders = $orders->whereNotIn('status', Order::NON_REVENUE_STATUSES);
        $grossRevenueTotal = (int) $revenueOrders->sum('total');

        $refunds = $this->refundsInRange($from, $to, $ignoreBranchScope, $branchId, $branchIds);
        $refundTotal = (int) $refunds->sum('amount');
        $refundsByDay = $this->groupByAccraDay($refunds, 'completed_at')->map->sum('amount');

        $discountTotal = PromotionRedemption::whereIn('order_id', $orders->pluck('id'))->sum('amount_discounted');

        $grossByDay = $this->fillDays($from, $to, $this->groupByAccraDay($revenueOrders)->map->sum('total'));
        $revenueByDay = $grossByDay->map(fn (int $gross, string $day) => $gross - (int) $refundsByDay->get($day, 0));

        return [
            'revenue_total' => $grossRevenueTotal - $refundTotal,
            'revenue_by_day' => $revenueByDay,
            'revenue_by_payment_method' => $revenueOrders->groupBy('payment_method')->map->sum('total'),
            'revenue_by_channel' => $revenueOrders->groupBy('channel')->map->sum('total'),
            'discount_total' => $discountTotal,
            'refund_total' => $refundTotal,
            'average_order_value' => $revenueOrders->isNotEmpty() ? (int) round($grossRevenueTotal / $revenueOrders->count()) : 0,
        ];
    }

    /**
     * Refunds *completed* within the window — see financialSummary()'s
     * docblock for why this is keyed on completed_at, not the original
     * order's placed_at. Same branch-scoping precedence as ordersInRange().
     *
     * @param  ?list<int>  $branchIds
     */
    private function refundsInRange(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): Collection
    {
        $query = Refund::where('status', Refund::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from, $to]);

        if ($ignoreBranchScope) {
            $query->withoutGlobalScope(BranchScope::class);
        } elseif ($branchIds !== null) {
            $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $branchIds);
        } elseif ($branchId !== null) {
            $query->withoutGlobalScope(BranchScope::class)->where('branch_id', $branchId);
        }

        return $query->get(['id', 'amount', 'completed_at']);
    }

    /**
     * $branchId/$branchIds are explicit overrides, independent of ambient
     * session state — $branchId is what lets the owner's per-branch
     * drilldown (and the branch-by-branch loop in
     * PerformanceController::branchBreakdown()) pin a single branch that
     * isn't necessarily the one selected in session (same reasoning as
     * itemSales' own $branchId param). $branchIds is the same idea for a
     * *set* of branches — general_manager's multi-branch oversight,
     * which is never "all branches" (that would silently grow to include
     * a branch they don't actually oversee the moment it's created) and
     * never exactly one (that's the regular manager's branch-switcher
     * model). Takes precedence over $branchId when both are given.
     */
    private function ordersInRange(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): Collection
    {
        $query = Order::whereBetween('placed_at', [$from, $to]);

        if ($ignoreBranchScope) {
            $query->withoutGlobalScope(BranchScope::class);
        } elseif ($branchIds !== null) {
            $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $branchIds);
        } elseif ($branchId !== null) {
            $query->withoutGlobalScope(BranchScope::class)->where('branch_id', $branchId);
        }

        return $query->get(['id', 'status', 'fulfilment_type', 'payment_method', 'channel', 'total', 'placed_at', 'accepted_at', 'ready_at', 'dispatched_at', 'delivered_at']);
    }

    /**
     * Public — PerformanceReportService reuses this exact bucketing for
     * visitor-traffic-by-day rather than duplicating it, since it already
     * holds this service as a dependency.
     */
    public function groupByAccraDay(Collection $items, string $dateColumn = 'placed_at'): Collection
    {
        return $items->groupBy(fn ($item) => $item->{$dateColumn}->timezone('Africa/Accra')->toDateString());
    }

    /**
     * Days with no orders are absent from a groupBy result — filled with 0
     * so the report shows a continuous range rather than silently skipping
     * quiet days.
     */
    public function fillDays(Carbon $from, Carbon $to, Collection $byDay): Collection
    {
        $period = CarbonPeriod::create($from->clone()->timezone('Africa/Accra'), $to->clone()->timezone('Africa/Accra'));

        return collect($period)->mapWithKeys(function (Carbon $day) use ($byDay) {
            $key = $day->toDateString();

            return [$key => $byDay->get($key, 0)];
        });
    }

    private function toMinutes(?float $seconds): ?float
    {
        return $seconds !== null ? round($seconds / 60, 1) : null;
    }
}
