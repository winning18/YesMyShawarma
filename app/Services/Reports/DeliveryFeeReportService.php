<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Delivery fees riders brought in — the single source for both the
 * Reports → Delivery fees tab and the rider app's own totals, so a rider's
 * week always equals their row in the report.
 *
 * Counts delivered delivery orders only (a failed delivery collects
 * nothing), dated by delivered_at, using the order's final delivery_fee —
 * which already includes a fee calculated at "Arrived", a manager
 * adjustment or a branch-transfer reprice.
 *
 * Fees aren't sales. Until 2026-11-11 every fee was the rider's (owner,
 * 2026-10-21); from then on riders hand in the cash fees they collect
 * (owner, 2026-11-11, RiderCashupService). Each fee is split by
 * where the money is:
 * - cash: taken from the customer at the door by the rider.
 * - prepaid: already with the business — Paystack covering the fee, or a
 *   POS MoMo order whose fee came with the food (delivery_fee_paid_by
 *   'momo', or unset). A Paystack order whose fee was only added at
 *   arrival is cash: Paystack was charged before the fee existed.
 *
 * Each row's collection status: 'kept' (delivered before hand-ins
 * started — the rider's), 'prepaid' (no cash to hand in), 'collected'
 * (in a live collection) or 'to_collect'.
 *
 * Branch arguments follow WeeklySalesReportService's convention, resolved
 * by ReportBranchResolver.
 */
class DeliveryFeeReportService
{
    private const FEE_CHANGE_ACTIONS = ['delivery_fee_adjusted', 'arrived', 'branch_transfer', 'delivery_fee_paid_by_changed'];

    public const STATUS_KEPT = 'kept';

    public const STATUS_PREPAID = 'prepaid';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_TO_COLLECT = 'to_collect';

    public const FEES_HANDED_IN = 'handed_in';

    public const FEES_KEPT_BY_RIDER = 'kept_by_rider';

    private ?Carbon $foodStartsOn = null;

    private ?Carbon $handInStartsOn = null;

    public function __construct(
        private readonly ReportBranchResolver $branches,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Start of the first day riders hand cash fees in (Africa/Accra).
     */
    public function handInStartsOn(): Carbon
    {
        return $this->handInStartsOn ??= Carbon::parse($this->settings->get(SettingsService::DELIVERY_FEE_HAND_IN_STARTS_ON) ?? '2026-11-11', 'Africa/Accra')->startOfDay();
    }

    /**
     * One row per delivered order, newest first.
     *
     * @param  ?list<int>  $branchIds
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Carbon $from, Carbon $to, ?int $riderId = null, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): Collection
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);

        return $this->query($from, $to)
            ->when($scopeIds !== null, fn (Builder $q) => $q->whereIn('orders.branch_id', $scopeIds))
            ->when($riderId !== null, fn (Builder $q) => $q->where('orders.rider_id', $riderId))
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => $this->row($order));
    }

    /**
     * A rider's own deliveries, at every branch they deliver for — their
     * money, whichever branch they were working at.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsForRider(User $rider, Carbon $from, Carbon $to): Collection
    {
        return $this->rows($from, $to, $rider->id, ignoreBranchScope: true);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     *                                                       to_collect / collected: what riders must hand in (rider cash-ups:
     *                                                       food, plus fees when handed in, less fees owed to riders).
     * @return array{total: int, cash: int, prepaid: int, count: int, to_collect: int, collected: int, food_cash: int, fee_income: int}
     */
    public function summary(Collection $rows): array
    {
        return [
            'total' => (int) $rows->sum('fee'),
            'cash' => (int) $rows->sum('cash'),
            'prepaid' => (int) $rows->sum('prepaid'),
            'count' => $rows->count(),
            'to_collect' => (int) $rows->where('collection_status', self::STATUS_TO_COLLECT)->sum('due'),
            'collected' => (int) $rows->where('collection_status', self::STATUS_COLLECTED)->sum('due'),
            'food_cash' => (int) $rows->sum('food_cash'),
            'fee_income' => (int) $rows->sum('fee_income'),
        ];
    }

    /**
     * One line per rider, highest total first.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{rider_id: int, rider: string, total: int, cash: int, prepaid: int, count: int}>
     */
    public function byRider(Collection $rows): Collection
    {
        return $rows->groupBy('rider_id')
            ->map(fn (Collection $riderRows, int $riderId) => [
                'rider_id' => $riderId,
                'rider' => $riderRows->first()['rider'],
                ...$this->summary($riderRows),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function query(Carbon $from, Carbon $to): Builder
    {
        return Order::query()
            // Scoped explicitly by the callers instead — a rider's own
            // totals span every branch they deliver for.
            ->withoutGlobalScope(BranchScope::class)
            ->where('fulfilment_type', 'delivery')
            ->where('status', 'delivered')
            ->whereNotNull('rider_id')
            ->whereBetween('delivered_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->with([
                'rider:id,name',
                'branch:id,name',
                'events' => fn ($q) => $q->whereIn('meta->action', self::FEE_CHANGE_ACTIONS),
                'cashupLine.cashup.collectedBy:id,name',
            ])
            ->withSum(['payments as paystack_paid' => fn ($q) => $q->where('provider', 'paystack')->where('status', 'paid')], 'amount')
            ->withSum(['refunds as refunded_total' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->where('status', Refund::STATUS_COMPLETED)], 'amount');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        $money = $this->money($order);
        $cashup = $order->cashupLine?->cashup;

        return [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'delivered_at' => $order->delivered_at->clone()->timezone('Africa/Accra'),
            'branch_id' => $order->branch_id,
            'branch' => $order->branch?->name,
            'rider_id' => $order->rider_id,
            'rider' => $order->rider?->name ?? __('(removed rider)'),
            'area' => $order->delivery_address_snapshot['area_name'] ?? null,
            'payment_method' => $order->payment_method,
            'channel' => $order->channel,
            ...$money,
            'fee_note' => $this->feeNote($order),
            'collection_status' => match (true) {
                $cashup !== null => self::STATUS_COLLECTED,
                $money['food_due'] === 0 && $money['fee_due'] === 0 && $money['fee_credit'] === 0
                    && $order->delivered_at->lt($this->handInStartsOn()) => self::STATUS_KEPT,
                $money['food_due'] === 0 && $money['fee_due'] === 0 && $money['fee_credit'] === 0 => self::STATUS_PREPAID,
                default => self::STATUS_TO_COLLECT,
            },
            'collection_id' => $cashup?->id,
            'collected_at' => $cashup?->collected_at?->clone()->timezone('Africa/Accra'),
            'collected_by' => $cashup?->collectedBy?->name,
        ];
    }

    /**
     * One delivered order's money, as the rider holds it (owner,
     * 2026-11-20):
     * - fee / cash / prepaid: the delivery fee, the part of it the rider
     *   took in cash at the door, and the part already with the business.
     * - food_cash: food money the rider took at the door (a cash order's
     *   food, or what Paystack didn't cover).
     * - food_due, fee_due, fee_credit, due: what the rider must hand in.
     *   Food always (from rider_cashup_food_starts_on); the fee too when
     *   fees are handed in (from the hand-in start date); when riders keep
     *   fees, a fee the business already holds is owed to the rider and
     *   comes off. due = food_due + fee_due - fee_credit, and can be
     *   negative: the business pays the rider.
     * - fee_income: the fee as the business's income (fees handed in
     *   only), for Sales and Profit.
     *
     * @return array{fee: int, cash: int, prepaid: int, food_cash: int, food_due: int, fee_due: int, fee_credit: int, due: int, fee_income: int}
     */
    public function money(Order $order): array
    {
        ['fee' => $fee, 'cash' => $cash, 'prepaid' => $prepaid, 'food_cash' => $foodCash] = $this->split($order);
        $delivered = $order->delivered_at;
        $feesCount = $delivered !== null && $delivered->gte($this->handInStartsOn());
        $foodStarts = $this->foodStartsOn();
        $handedIn = $this->feesHandedIn();

        $foodDue = ($foodStarts === null || ($delivered !== null && $delivered->gte($foodStarts))) ? $foodCash : 0;
        $feeDue = $handedIn && $feesCount ? $cash : 0;
        $feeCredit = ! $handedIn && $feesCount ? $prepaid : 0;

        return [
            'fee' => $fee,
            'cash' => $cash,
            'prepaid' => $prepaid,
            'food_cash' => $foodCash,
            'food_due' => $foodDue,
            'fee_due' => $feeDue,
            'fee_credit' => $feeCredit,
            'due' => $foodDue + $feeDue - $feeCredit,
            'fee_income' => $handedIn && $feesCount ? $fee : 0,
        ];
    }

    /**
     * One delivered order's fee, split into what the rider got from the
     * customer in cash and what staff pay them (see the class docblock),
     * and the food money the rider took at the door.
     *
     * @return array{fee: int, cash: int, prepaid: int, food_cash: int}
     */
    public function split(Order $order): array
    {
        $fee = (int) $order->delivery_fee;

        // Callers that list many orders preload this with withSum(); null
        // there just means no Paystack payment.
        $paystackPaid = array_key_exists('paystack_paid', $order->getAttributes())
            ? $order->paystack_paid
            : $order->payments()->where('provider', 'paystack')->where('status', 'paid')->sum('amount');

        // Everything the rider took at the door, then the fee's share of it.
        $atDoor = match ($order->payment_method) {
            'cash' => (int) $order->total,
            'paystack' => max(0, (int) $order->total - (int) $paystackPaid),
            // A freebie's food is free; its fee is collected like a MoMo order's (2026-11-20).
            'momo', Order::COMPLIMENTARY => $order->delivery_fee_paid_by === 'rider_cash' ? $fee : 0,
            default => 0,
        };
        $cash = min($fee, $atDoor);
        $foodCash = $atDoor - $cash;
        $prepaid = $fee - $cash;

        // A refund gives back the food first; whatever it returns beyond
        // that is the delivery fee, which comes off the rider's figures
        // (owner, 2026-11-10: a full refund includes the fee) — cash first,
        // since a cash refund is the rider handing that money back.
        $refunded = array_key_exists('refunded_total', $order->getAttributes())
            ? (int) $order->refunded_total
            : (int) $order->refunds()->withoutGlobalScope(BranchScope::class)->where('status', Refund::STATUS_COMPLETED)->sum('amount');
        $food = (int) $order->total - $fee;
        $foodRefunded = min(max(0, $food), $refunded);
        $feeRefunded = min($fee, max(0, $refunded - $food));
        $fromCash = min($cash, $feeRefunded);

        return [
            'fee' => $fee - $feeRefunded,
            'cash' => $cash - $fromCash,
            'prepaid' => $prepaid - ($feeRefunded - $fromCash),
            'food_cash' => $foodCash - min($foodCash, $foodRefunded),
        ];
    }

    /** Riders hand delivery fees in (the default), or keep them as their pay. */
    public function feesHandedIn(): bool
    {
        return $this->settings->get(SettingsService::DELIVERY_FEE_HANDLING, self::FEES_HANDED_IN) !== self::FEES_KEPT_BY_RIDER;
    }

    /** From when food money comes in through rider cash-ups; null: always. */
    public function foodStartsOn(): ?Carbon
    {
        $date = $this->settings->get(SettingsService::RIDER_CASHUP_FOOD_STARTS_ON);

        return $this->foodStartsOn ??= $date ? Carbon::parse($date, 'Africa/Accra')->startOfDay() : null;
    }

    /**
     * Why an order's fee isn't simply the checkout price, for the audit
     * trail — read off the order_events each fee change already writes.
     */
    private function feeNote(Order $order): ?string
    {
        $notes = $order->events->map(fn ($event) => match ($event->meta['action'] ?? null) {
            'delivery_fee_adjusted' => __('Adjusted by manager'),
            'arrived' => ($event->meta['delivery_fee_calculated'] ?? false) ? __('Set on arrival') : null,
            'branch_transfer' => ($event->meta['delivery_fee_old'] ?? null) !== ($event->meta['delivery_fee_new'] ?? null) ? __('Changed on transfer') : null,
            'delivery_fee_paid_by_changed' => __('Payment method changed'),
            default => null,
        })->filter()->unique()->values();

        return $notes->isEmpty() ? null : $notes->implode(', ');
    }
}
