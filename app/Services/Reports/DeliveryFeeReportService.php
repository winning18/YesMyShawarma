<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Models\User;
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
 * Every delivery fee goes to the rider — fees aren't sales (owner,
 * 2026-10-21). Each one is split by where the rider gets it:
 * - cash: from the customer at the door.
 * - prepaid: the business already holds it, so the staff on shift pay the
 *   rider — Paystack covering the fee, or a POS MoMo order whose fee came
 *   with the food (delivery_fee_paid_by 'momo', or unset). A Paystack
 *   order whose fee was only added at arrival is cash: Paystack was
 *   charged before the fee existed.
 *
 * Branch arguments follow WeeklySalesReportService's convention, resolved
 * by ReportBranchResolver.
 */
class DeliveryFeeReportService
{
    private const FEE_CHANGE_ACTIONS = ['delivery_fee_adjusted', 'arrived', 'branch_transfer', 'delivery_fee_paid_by_changed'];

    public function __construct(private readonly ReportBranchResolver $branches) {}

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
     * @return array{total: int, cash: int, prepaid: int, count: int}
     */
    public function summary(Collection $rows): array
    {
        return [
            'total' => (int) $rows->sum('fee'),
            'cash' => (int) $rows->sum('cash'),
            'prepaid' => (int) $rows->sum('prepaid'),
            'count' => $rows->count(),
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
            ])
            ->withSum(['payments as paystack_paid' => fn ($q) => $q->where('provider', 'paystack')->where('status', 'paid')], 'amount');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        ['fee' => $fee, 'cash' => $cash, 'prepaid' => $prepaid] = $this->split($order);

        return [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'delivered_at' => $order->delivered_at->clone()->timezone('Africa/Accra'),
            'branch' => $order->branch?->name,
            'rider_id' => $order->rider_id,
            'rider' => $order->rider?->name ?? __('(removed rider)'),
            'area' => $order->delivery_address_snapshot['area_name'] ?? null,
            'payment_method' => $order->payment_method,
            'fee' => $fee,
            'cash' => $cash,
            'prepaid' => $prepaid,
            'fee_note' => $this->feeNote($order),
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

        return ['fee' => $fee, 'cash' => $cash, 'prepaid' => $fee - $cash];
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
