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
 * (owner, 2026-11-11, DeliveryFeeCollectionService). Each fee is split by
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
     * @return array{total: int, cash: int, prepaid: int, count: int, to_collect: int, collected: int}
     */
    public function summary(Collection $rows): array
    {
        return [
            'total' => (int) $rows->sum('fee'),
            'cash' => (int) $rows->sum('cash'),
            'prepaid' => (int) $rows->sum('prepaid'),
            'count' => $rows->count(),
            'to_collect' => (int) $rows->where('collection_status', self::STATUS_TO_COLLECT)->sum('cash'),
            'collected' => (int) $rows->where('collection_status', self::STATUS_COLLECTED)->sum('cash'),
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
                'feeCollection.collection.collectedBy:id,name',
            ])
            ->withSum(['payments as paystack_paid' => fn ($q) => $q->where('provider', 'paystack')->where('status', 'paid')], 'amount')
            ->withSum(['refunds as refunded_total' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->where('status', Refund::STATUS_COMPLETED)], 'amount');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        ['fee' => $fee, 'cash' => $cash, 'prepaid' => $prepaid] = $this->split($order);
        $collection = $order->feeCollection?->collection;

        return [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'delivered_at' => $order->delivered_at->clone()->timezone('Africa/Accra'),
            'branch' => $order->branch?->name,
            'rider_id' => $order->rider_id,
            'rider' => $order->rider?->name ?? __('(removed rider)'),
            'area' => $order->delivery_address_snapshot['area_name'] ?? null,
            'payment_method' => $order->payment_method,
            'channel' => $order->channel,
            'fee' => $fee,
            'cash' => $cash,
            'prepaid' => $prepaid,
            'fee_note' => $this->feeNote($order),
            'collection_status' => match (true) {
                $order->delivered_at->lt($this->handInStartsOn()) => self::STATUS_KEPT,
                $collection !== null => self::STATUS_COLLECTED,
                $cash === 0 => self::STATUS_PREPAID,
                default => self::STATUS_TO_COLLECT,
            },
            'collection_id' => $collection?->id,
            'collected_at' => $collection?->collected_at?->clone()->timezone('Africa/Accra'),
            'collected_by' => $collection?->collectedBy?->name,
        ];
    }

    /**
     * One delivered order's fee, split into what the rider got from the
     * customer in cash and what staff pay them (see the class docblock).
     *
     * @return array{fee: int, cash: int, prepaid: int}
     */
    public function split(Order $order): array
    {
        $fee = (int) $order->delivery_fee;

        // Callers that list many orders preload this with withSum(); null
        // there just means no Paystack payment.
        $paystackPaid = array_key_exists('paystack_paid', $order->getAttributes())
            ? $order->paystack_paid
            : $order->payments()->where('provider', 'paystack')->where('status', 'paid')->sum('amount');

        $cash = match ($order->payment_method) {
            'cash' => $fee,
            'paystack' => min($fee, max(0, (int) $order->total - (int) $paystackPaid)),
            'momo' => $order->delivery_fee_paid_by === 'rider_cash' ? $fee : 0,
            default => 0,
        };
        $prepaid = $fee - $cash;

        // A refund gives back the food first; whatever it returns beyond
        // that is the delivery fee, which comes off the rider's figures
        // (owner, 2026-11-10: a full refund includes the fee) — cash first,
        // since a cash refund is the rider handing that money back.
        $refunded = array_key_exists('refunded_total', $order->getAttributes())
            ? (int) $order->refunded_total
            : (int) $order->refunds()->withoutGlobalScope(BranchScope::class)->where('status', Refund::STATUS_COMPLETED)->sum('amount');
        $feeRefunded = min($fee, max(0, $refunded - ((int) $order->total - $fee)));
        $fromCash = min($cash, $feeRefunded);

        return ['fee' => $fee - $feeRefunded, 'cash' => $cash - $fromCash, 'prepaid' => $prepaid - ($feeRefunded - $fromCash)];
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
