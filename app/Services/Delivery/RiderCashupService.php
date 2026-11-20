<?php

namespace App\Services\Delivery;

use App\Exceptions\RiderCashupException;
use App\Models\Order;
use App\Models\RiderCashup;
use App\Models\Scopes\BranchScope;
use App\Models\Shift;
use App\Models\User;
use App\Services\Reports\DeliveryFeeReportService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rider cash-ups (owner, 2026-11-20): at the end of the day a rider hands
 * in, at a till, everything they must account for (DeliveryFeeReportService
 * ::money()): the food money they collected at doors, always; the delivery
 * fees too when the business has fees handed in; less the fees the business
 * already holds for a rider who keeps them. What was actually handed over
 * is recorded beside what was due, so a shortfall shows.
 *
 * The till that receives it counts it in its cash-up (tillAdjustment()):
 * the food money of a delivery the rider collects leaves the till that
 * took the order and joins the one the rider hands it to.
 *
 * One rider, one branch, one period: every delivery of theirs there not yet
 * handed in. Each delivery goes into one live cash-up at most; voiding (a
 * mistake, with a reason) frees its deliveries again and keeps the record.
 * Before 2026-11-20 these were fee-only "delivery fee collections"; those
 * are the first cash-ups.
 */
class RiderCashupService
{
    public function __construct(private readonly DeliveryFeeReportService $fees) {}

    /**
     * What this rider owes at this branch for these dates.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, expected: int, food: int, fee: int, fee_credit: int, count: int}
     */
    public function owed(User $rider, int $branchId, Carbon $from, Carbon $to): array
    {
        $rows = $this->fees->rows($from, $to, $rider->id, branchId: $branchId)
            ->where('collection_status', DeliveryFeeReportService::STATUS_TO_COLLECT)
            ->values();

        return [
            'rows' => $rows,
            'expected' => (int) $rows->sum('due'),
            'food' => (int) $rows->sum('food_due'),
            'fee' => (int) $rows->sum('fee_due'),
            'fee_credit' => (int) $rows->sum('fee_credit'),
            'count' => $rows->count(),
        ];
    }

    /**
     * @param  ?Shift  $till  the till receiving the money (its cash-up counts it); null: none open
     */
    public function record(User $collector, User $rider, int $branchId, Carbon $from, Carbon $to, int $received, ?string $note = null, ?Shift $till = null): RiderCashup
    {
        $owed = $this->owed($rider, $branchId, $from, $to);

        if ($owed['count'] === 0) {
            throw RiderCashupException::nothingToCollect();
        }

        try {
            return DB::transaction(function () use ($collector, $rider, $branchId, $from, $to, $received, $note, $owed, $till) {
                $cashup = RiderCashup::create([
                    'branch_id' => $branchId,
                    'rider_id' => $rider->id,
                    'shift_id' => $till?->branch_id === $branchId ? $till->id : null,
                    'period_from' => $from->toDateString(),
                    'period_to' => $to->toDateString(),
                    'expected_food' => $owed['food'],
                    'expected_fee' => $owed['fee'],
                    'fee_credit' => $owed['fee_credit'],
                    'fee_handling' => $this->fees->feesHandedIn() ? DeliveryFeeReportService::FEES_HANDED_IN : DeliveryFeeReportService::FEES_KEPT_BY_RIDER,
                    'expected' => $owed['expected'],
                    'received' => $received,
                    'order_count' => $owed['count'],
                    'note' => $note,
                    'collected_by' => $collector->id,
                    'collected_at' => now(),
                ]);

                $cashup->orders()->createMany($owed['rows']->map(fn (array $row) => [
                    'order_id' => $row['order_id'],
                    'food_amount' => $row['food_due'],
                    'fee_amount' => $row['fee_due'],
                    'fee_credit' => $row['fee_credit'],
                ])->all());

                return $cashup;
            });
        } catch (UniqueConstraintViolationException) {
            throw RiderCashupException::alreadyCollected();
        }
    }

    public function void(RiderCashup $cashup, User $user, string $reason): RiderCashup
    {
        if ($cashup->voided_at !== null) {
            throw RiderCashupException::alreadyVoided();
        }

        DB::transaction(function () use ($cashup, $user, $reason) {
            $cashup->orders()->delete();
            $cashup->update(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason]);
        });

        return $cashup->fresh();
    }

    /**
     * How a till's expected takings move with rider cash-ups: plus what
     * riders handed in at it (net, as recorded due), minus the food money
     * riders collect for its own delivery orders, which reaches a till
     * only through a cash-up (from rider_cashup_food_starts_on).
     *
     * @param  Collection<int, Order>  $orders  the orders the till's sales were worked out from
     */
    public function tillAdjustment(Shift $till, Collection $orders): int
    {
        $handedIn = (int) RiderCashup::withoutGlobalScope(BranchScope::class)
            ->where('shift_id', $till->id)
            ->whereNull('voided_at')
            ->sum('expected');

        return $handedIn - $this->foodCollectedByRiders($orders);
    }

    /**
     * The food money riders collect at the door for these orders, whether
     * or not they've delivered yet (from rider_cashup_food_starts_on).
     *
     * @param  Collection<int, Order>  $orders
     */
    public function foodCollectedByRiders(Collection $orders): int
    {
        $ids = $orders->filter(fn (Order $order) => $order->fulfilment_type === 'delivery'
            && in_array($order->payment_method, ['cash', 'paystack'], true)
            && ! in_array($order->status, [...Order::NON_REVENUE_STATUSES, 'failed'], true))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $starts = $this->fees->foodStartsOn();

        return (int) Order::withoutGlobalScope(BranchScope::class)
            ->whereIn('id', $ids)
            ->when($starts, fn ($q) => $q->where(fn ($q) => $q->whereNull('delivered_at')->orWhere('delivered_at', '>=', $starts->clone()->utc())))
            ->withSum(['payments as paystack_paid' => fn ($q) => $q->where('provider', 'paystack')->where('status', 'paid')], 'amount')
            ->withSum(['refunds as refunded_total' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->where('status', 'completed')], 'amount')
            ->get()
            ->sum(fn (Order $order) => $this->fees->split($order)['food_cash']);
    }
}
