<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PromotionRedemption;
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
    public function operationalSummary(Carbon $from, Carbon $to, bool $ignoreBranchScope = false): array
    {
        $orders = $this->ordersInRange($from, $to, $ignoreBranchScope);
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

    public function financialSummary(Carbon $from, Carbon $to, bool $ignoreBranchScope = false): array
    {
        $orders = $this->ordersInRange($from, $to, $ignoreBranchScope);
        $revenueOrders = $orders->whereNotIn('status', Order::NON_REVENUE_STATUSES);

        $revenueTotal = $revenueOrders->sum('total');
        $discountTotal = PromotionRedemption::whereIn('order_id', $orders->pluck('id'))->sum('amount_discounted');
        $refundTotal = $orders->where('status', 'refunded')->sum('total');

        return [
            'revenue_total' => $revenueTotal,
            'revenue_by_day' => $this->fillDays($from, $to, $this->groupByAccraDay($revenueOrders)->map->sum('total')),
            'revenue_by_payment_method' => $revenueOrders->groupBy('payment_method')->map->sum('total'),
            'revenue_by_channel' => $revenueOrders->groupBy('channel')->map->sum('total'),
            'discount_total' => $discountTotal,
            'refund_total' => $refundTotal,
            'average_order_value' => $revenueOrders->isNotEmpty() ? (int) round($revenueTotal / $revenueOrders->count()) : 0,
        ];
    }

    private function ordersInRange(Carbon $from, Carbon $to, bool $ignoreBranchScope = false): Collection
    {
        $query = Order::whereBetween('placed_at', [$from, $to]);

        if ($ignoreBranchScope) {
            $query->withoutGlobalScope(BranchScope::class);
        }

        return $query->get(['id', 'status', 'fulfilment_type', 'payment_method', 'channel', 'total', 'placed_at', 'accepted_at', 'ready_at', 'dispatched_at', 'delivered_at']);
    }

    private function groupByAccraDay(Collection $orders): Collection
    {
        return $orders->groupBy(fn (Order $o) => $o->placed_at->timezone('Africa/Accra')->toDateString());
    }

    /**
     * Days with no orders are absent from a groupBy result — filled with 0
     * so the report shows a continuous range rather than silently skipping
     * quiet days.
     */
    private function fillDays(Carbon $from, Carbon $to, Collection $byDay): Collection
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
