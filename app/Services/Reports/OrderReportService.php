<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PromotionRedemption;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Models\Shift;
use App\Services\Delivery\RiderCashupService;
use App\Services\Tax\TaxService;
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
            // Bolt Food orders are always channel 'pos' (payments.md), so
            // a plain countBy('channel') would silently fold them into the
            // same bucket as orders actually taken at the in-house POS
            // till — reclassified into their own 'bolt_food' bucket here
            // so "pos" means only real in-house counter/phone orders.
            'orders_by_channel' => $orders->countBy(
                fn (Order $order) => match ($order->payment_method) {
                    Order::BOLT_FOOD => 'bolt_food',
                    Order::COMPLIMENTARY => 'freebie',
                    default => $order->channel,
                }
            ),
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
     * $revenueOrders also excludes Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS
     * (Bolt Food) — this is what makes the shift-end system_sales check
     * (ShiftController, which calls this same method) never ask staff to
     * reconcile money that never touched their till in the first place.
     * $paymentMethod set (e.g. the Sales page's explicit "Bolt Food" view)
     * means the caller wants exactly that method counted — including Bolt
     * Food, which is otherwise always excluded — so the exclusion is
     * skipped rather than fighting an explicit request for it.
     *
     * $channel/$paymentMethod let this serve as the Sales page's own
     * "total sales" figure (`DailySalesReportService::summary()`) so both
     * surfaces agree on one net-of-refunds number instead of maintaining
     * two independent formulas for the same thing.
     *
     * @param  ?list<int>  $branchIds  See operationalSummary().
     */
    public function financialSummary(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null, ?string $channel = null, ?string $paymentMethod = null, ?int $shiftId = null): array
    {
        $orders = $this->ordersInRange($from, $to, $ignoreBranchScope, $branchId, $branchIds, $channel, $paymentMethod, $shiftId);
        $revenueOrders = $orders->whereNotIn('status', Order::NON_REVENUE_STATUSES)
            ->when(
                $paymentMethod,
                fn ($query) => $query,
                fn ($query) => $query->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS),
            );
        // Delivery fees are the rider's, never sales (payments.md's
        // "Delivery fee audit") — every figure here is food after
        // discounts, and before tax (step 9): tax is owed, not earned.
        // till_total is the same food with its tax, what a till holds. A
        // refund only takes back food up to what was paid for it, so
        // refunding a whole order can't push sales below zero; its tax
        // share comes off tax_total.
        $refunds = $this->refundsInRange($from, $to, $ignoreBranchScope, $branchId, $branchIds, $channel, $paymentMethod, $shiftId);
        $taxService = app(TaxService::class);
        $tax = $taxService->breakdown($revenueOrders->concat($refunds->pluck('order')->filter()));

        $salesValue = fn (Order $order) => $taxService->netFood($order, $tax);
        $tillValue = fn (Order $order) => $taxService->grossFood($order, $tax);
        $tillRefunded = fn (Refund $refund) => $refund->order
            ? min((int) $refund->amount, max(0, $tillValue($refund->order)))
            : (int) $refund->amount;
        $salesRefunded = function (Refund $refund) use ($tillRefunded, $tillValue, $salesValue) {
            $refunded = $tillRefunded($refund);
            $gross = $refund->order ? $tillValue($refund->order) : 0;

            return $gross > 0 ? (int) round($refunded * $salesValue($refund->order) / $gross) : $refunded;
        };

        $grossRevenueTotal = (int) $revenueOrders->sum($salesValue);
        $tillTotal = (int) $revenueOrders->sum($tillValue);

        $refundTotal = (int) $refunds->sum('amount');
        $salesRefundedTotal = (int) $refunds->sum($salesRefunded);
        $tillRefundedTotal = (int) $refunds->sum($tillRefunded);
        $refundsByDay = $this->groupByAccraDay($refunds, 'completed_at')->map(fn (Collection $day) => $day->sum($salesRefunded));

        $discountTotal = PromotionRedemption::whereIn('order_id', $orders->pluck('id'))->sum('amount_discounted');

        $grossByDay = $this->fillDays($from, $to, $this->groupByAccraDay($revenueOrders)->map(fn (Collection $day) => $day->sum($salesValue)));
        $revenueByDay = $grossByDay->map(fn (int $gross, string $day) => $gross - (int) $refundsByDay->get($day, 0));

        return [
            'revenue_total' => $grossRevenueTotal - $salesRefundedTotal,
            'till_total' => $tillTotal - $tillRefundedTotal,
            'tax_total' => ($tillTotal - $tillRefundedTotal) - ($grossRevenueTotal - $salesRefundedTotal),
            'revenue_by_day' => $revenueByDay,
            'revenue_by_payment_method' => $revenueOrders->groupBy('payment_method')->map(fn (Collection $orders) => $orders->sum($salesValue)),
            'revenue_by_channel' => $revenueOrders->groupBy('channel')->map(fn (Collection $orders) => $orders->sum($salesValue)),
            'discount_total' => $discountTotal,
            'refund_total' => $refundTotal,
            'average_order_value' => $revenueOrders->isNotEmpty() ? (int) round($grossRevenueTotal / $revenueOrders->count()) : 0,
        ];
    }

    /**
     * A counter's till (Express POS step 7a): the sales of the orders that
     * till took (orders.shift_id), less what was refunded on them — the
     * same food-only, net-of-refunds measure as financialSummary(), but by
     * till rather than by time, since several tills are open at once.
     * With any tax the food was sold with (step 9): what the till holds.
     */
    public function tillSales(Shift $shift): int
    {
        return $this->financialSummary($shift->started_at, now(), shiftId: $shift->id)['till_total']
            + app(RiderCashupService::class)->tillAdjustment($shift, $this->ordersInRange($shift->started_at, now(), shiftId: $shift->id));
    }

    /**
     * A branch's one shared till (no counters): its sales since it opened,
     * with the same rider cash-up adjustment as tillSales(). Branch from
     * the current BranchContext, as ShiftController always has it.
     */
    public function sharedTillSales(Shift $shift): int
    {
        return $this->financialSummary($shift->started_at, now())['till_total']
            + app(RiderCashupService::class)->tillAdjustment($shift, $this->ordersInRange($shift->started_at, now()));
    }

    /**
     * Refunds *completed* within the window — see financialSummary()'s
     * docblock for why this is keyed on completed_at, not the original
     * order's placed_at. Same branch-scoping precedence as ordersInRange().
     * $channel/$paymentMethod (same meaning as financialSummary()'s own)
     * are matched via the refund's own order — Refund carries no channel/
     * payment_method column of its own, and the order's placed_at can fall
     * well outside [$from, $to] (it's the refund, not the order, keyed to
     * this window), so this can't reuse ordersInRange()'s own result set.
     *
     * @param  ?list<int>  $branchIds
     */
    private function refundsInRange(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null, ?string $channel = null, ?string $paymentMethod = null, ?int $shiftId = null): Collection
    {
        // One counter's till: every completed refund on the orders it took —
        // except a return given back at another till (R3: refunds.shift_id
        // names that till), and plus the returns this till gave back on
        // other tills' sales.
        if ($shiftId !== null) {
            $tillOrders = fn () => Order::withoutGlobalScope(BranchScope::class)->where('shift_id', $shiftId)
                ->when($channel, fn ($query) => $query->where('channel', $channel))
                ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod))
                ->select('id');
            $anyOrder = fn () => Order::withoutGlobalScope(BranchScope::class)
                ->when($channel, fn ($query) => $query->where('channel', $channel))
                ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod))
                ->select('id');

            return Refund::where('status', Refund::STATUS_COMPLETED)
                ->where(fn ($q) => $q
                    ->where(fn ($q) => $q->whereNull('shift_id')->whereIn('order_id', $tillOrders()))
                    ->orWhere(fn ($q) => $q->where('shift_id', $shiftId)->whereIn('order_id', $anyOrder())))
                ->with(['order' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->select('id', 'subtotal', 'discount_total', 'tax_total', 'total', 'delivery_fee')])
                ->get();
        }

        $query = Refund::where('status', Refund::STATUS_COMPLETED)
            ->with(['order' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->select('id', 'subtotal', 'discount_total', 'tax_total', 'total', 'delivery_fee')])
            ->whereBetween('completed_at', [$from, $to])
            ->when(
                $channel || $paymentMethod,
                // Order carries its own BranchScope, which would otherwise
                // silently re-filter this by whatever branch happens to be
                // ambient in session — branch scoping for this query is
                // already fully handled below via $ignoreBranchScope/
                // $branchId/$branchIds, so the related-order lookup here is
                // channel/payment_method matching only, not a second
                // (and differently-scoped) branch filter.
                fn ($query) => $query->whereHas('order', fn ($q) => $q
                    ->withoutGlobalScope(BranchScope::class)
                    ->when($channel, fn ($q) => $q->where('channel', $channel))
                    ->when($paymentMethod, fn ($q) => $q->where('payment_method', $paymentMethod))),
            );

        if ($ignoreBranchScope) {
            $query->withoutGlobalScope(BranchScope::class);
        } elseif ($branchIds !== null) {
            $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $branchIds);
        } elseif ($branchId !== null) {
            $query->withoutGlobalScope(BranchScope::class)->where('branch_id', $branchId);
        }

        return $query->get(['id', 'order_id', 'amount', 'completed_at']);
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
    private function ordersInRange(Carbon $from, Carbon $to, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null, ?string $channel = null, ?string $paymentMethod = null, ?int $shiftId = null): Collection
    {
        // One counter's till (step 7a): the orders it took, whenever they
        // were placed — a web order can be placed before the till opened
        // and still be accepted into it.
        if ($shiftId !== null) {
            return Order::withoutGlobalScope(BranchScope::class)->where('shift_id', $shiftId)
                ->when($channel, fn ($query) => $query->where('channel', $channel))
                ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod))
                ->get(['id', 'status', 'fulfilment_type', 'payment_method', 'channel', 'subtotal', 'discount_total', 'tax_total', 'total', 'delivery_fee', 'placed_at', 'accepted_at', 'ready_at', 'dispatched_at', 'delivered_at']);
        }

        $query = Order::whereBetween('placed_at', [$from, $to])
            ->when($channel, fn ($query) => $query->where('channel', $channel))
            ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod));

        if ($ignoreBranchScope) {
            $query->withoutGlobalScope(BranchScope::class);
        } elseif ($branchIds !== null) {
            $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $branchIds);
        } elseif ($branchId !== null) {
            $query->withoutGlobalScope(BranchScope::class)->where('branch_id', $branchId);
        }

        return $query->get(['id', 'status', 'fulfilment_type', 'payment_method', 'channel', 'subtotal', 'discount_total', 'tax_total', 'total', 'delivery_fee', 'placed_at', 'accepted_at', 'ready_at', 'dispatched_at', 'delivered_at']);
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
