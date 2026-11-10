<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Services\Orders\RefundService;
use App\Services\Reports\DeliveryFeeReportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeliveryHistoryController extends Controller
{
    public function __construct(
        private readonly DeliveryFeeReportService $fees,
        private readonly RefundService $refunds,
    ) {}

    /**
     * Past deliveries only — "delivered" or "failed" (see orders.md; no
     * dedicated failed_at column exists, so this orders by updated_at
     * rather than a per-status timestamp). Active orders live on the main
     * rider dashboard, not here.
     *
     * The delivery-fee totals come from DeliveryFeeReportService — the
     * same figures a manager sees for this rider under Reports → Delivery
     * fees.
     */
    public function index(Request $request): View
    {
        $rider = $request->user();
        $now = Carbon::now('Africa/Accra');
        [$from, $to] = $this->resolveRange($request, $now);

        return view('rider.history', [
            'orders' => $this->history($request),
            'today' => $this->fees->summary($this->fees->rowsForRider($rider, $now->clone()->startOfDay(), $now->clone()->endOfDay())),
            'thisWeek' => $this->fees->summary($this->fees->rowsForRider($rider, $now->clone()->startOfWeek(), $now->clone()->endOfWeek())),
            'range' => $this->fees->summary($this->fees->rowsForRider($rider, $from, $to)),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Every branch this rider delivered for, not just the one they're
     * signed in at — the totals above span them all too, so the list and
     * the totals always describe the same deliveries.
     */
    private function history(Request $request): LengthAwarePaginator
    {
        return Order::withoutGlobalScope(BranchScope::class)
            ->with(['items', 'refunds' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->latest('id')])
            ->withSum(['payments as paystack_paid' => fn ($q) => $q->where('provider', 'paystack')->where('status', 'paid')], 'amount')
            ->withSum(['refunds as refunded_total' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->where('status', Refund::STATUS_COMPLETED)], 'amount')
            ->where('rider_id', $request->user()->id)
            ->whereIn('status', ['delivered', 'failed'])
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Order $order) {
                // Only a delivered delivery order carries a fee the rider
                // brought in — same rule as the totals.
                $order->setAttribute('fee_split', $order->status === 'delivered' && $order->fulfilment_type === 'delivery'
                    ? $this->fees->split($order)
                    : null);

                // The refund this rider sees on the card: the latest one,
                // whether they can ask for one, and whether an approved one
                // is theirs to hand back (cash) — OrderPolicy/RefundPolicy.
                $refund = $order->refunds->first();
                $open = $refund && in_array($refund->status, [Refund::STATUS_PENDING, Refund::STATUS_APPROVED], true);
                $order->setAttribute('refund_card', [
                    'refund' => $refund,
                    'can_request' => ! $open && Gate::allows('requestRiderRefund', $order),
                    'can_give' => $refund?->status === Refund::STATUS_APPROVED && Gate::allows('completeAsRider', $refund),
                    'remaining' => $this->refunds->remainingBalance($order),
                ]);

                return $order;
            });
    }

    /**
     * The "choose dates" totals — last week by default, the week a rider
     * typically settles up for.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request, Carbon $now): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if (! isset($validated['from']) && ! isset($validated['to'])) {
            return [$now->clone()->subWeek()->startOfWeek(), $now->clone()->subWeek()->endOfWeek()];
        }

        $from = Carbon::parse($validated['from'] ?? $validated['to'], 'Africa/Accra')->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $validated['from'], 'Africa/Accra')->endOfDay();

        return $from->gt($to) ? [$to->clone()->startOfDay(), $from->clone()->endOfDay()] : [$from, $to];
    }
}
