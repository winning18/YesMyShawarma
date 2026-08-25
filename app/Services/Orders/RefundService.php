<?php

namespace App\Services\Orders;

use App\Exceptions\RefundException;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\Payments\PaystackClient;
use Illuminate\Support\Facades\DB;

/**
 * "Refunds are initiated through Paystack's API, never by hand-editing
 * status" (payments.md) — for a paystack-paid order, complete() calls
 * Paystack's refund endpoint against the original charge's payments row.
 * Cash/momo orders have no gateway to call; completing one just records
 * the refund — the actual cash/momo handback happens outside the app,
 * same as how those methods are collected in the first place.
 *
 * Deliberately never touches orders.status or OrderStateMachine — a
 * refund is a financial ledger event, not a change to the order's
 * operational workflow. An order that was legitimately delivered stays
 * "delivered" after being refunded; order_events plus this table are
 * what say a refund happened, not the order's own status column. This
 * also sidesteps a real trap: flipping status to 'refunded' would drop
 * the order out of Order::NON_REVENUE_STATUSES-based gross-revenue
 * queries for the day it was *placed*, when what should actually happen
 * is gross revenue stays put and the refund is subtracted on the day it
 * was *completed* (see OrderReportService::financialSummary()).
 */
class RefundService
{
    public function __construct(private readonly PaystackClient $paystack) {}

    /**
     * order.total minus every refund actually completed against it —
     * pending/denied requests never reserve budget (see the class docblock
     * on why over-requesting is tolerated at request time and only hard-
     * blocked here and at completion time).
     */
    public function remainingBalance(Order $order): int
    {
        $completed = Refund::where('order_id', $order->id)
            ->where('status', Refund::STATUS_COMPLETED)
            ->sum('amount');

        return $order->total - $completed;
    }

    public function request(Order $order, User $requester, int $amount, string $reason, string $actorType, ?int $shiftId = null): Refund
    {
        return DB::transaction(function () use ($order, $requester, $amount, $reason, $actorType, $shiftId) {
            $this->assertRefundable($order, $amount);

            $refund = Refund::create([
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'amount' => $amount,
                'reason' => $reason,
                'status' => Refund::STATUS_PENDING,
                'requested_by' => $requester->id,
            ]);

            $this->logEvent($order, $actorType, $requester->id, $shiftId, [
                'action' => 'refund_requested', 'refund_id' => $refund->id, 'amount' => $amount,
            ]);

            return $refund;
        });
    }

    public function approve(Refund $refund, User $reviewer, ?string $note): Refund
    {
        $this->assertStatus($refund, Refund::STATUS_PENDING);

        $refund->update([
            'status' => Refund::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->logEvent($refund->order, 'owner', $reviewer->id, null, [
            'action' => 'refund_approved', 'refund_id' => $refund->id,
        ]);

        return $refund->fresh();
    }

    public function deny(Refund $refund, User $reviewer, ?string $note): Refund
    {
        $this->assertStatus($refund, Refund::STATUS_PENDING);

        $refund->update([
            'status' => Refund::STATUS_DENIED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->logEvent($refund->order, 'owner', $reviewer->id, null, [
            'action' => 'refund_denied', 'refund_id' => $refund->id,
        ]);

        return $refund->fresh();
    }

    /**
     * The owner's one-click path — request + approve + complete in one
     * transaction, so "restrict refund rights to only admin" doesn't force
     * the one person exempt from needing approval to approve their own
     * request as pointless ceremony.
     */
    public function directRefund(Order $order, User $owner, int $amount, string $reason, string $actorType, ?int $shiftId = null): Refund
    {
        return DB::transaction(function () use ($order, $owner, $amount, $reason, $actorType, $shiftId) {
            $refund = $this->request($order, $owner, $amount, $reason, $actorType, $shiftId);

            $refund->update([
                'status' => Refund::STATUS_APPROVED,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
            ]);

            return $this->complete($refund, $owner, $actorType, $shiftId);
        });
    }

    public function complete(Refund $refund, User $actor, string $actorType, ?int $shiftId = null): Refund
    {
        return DB::transaction(function () use ($refund, $actor, $actorType, $shiftId) {
            // Lock the order row for the duration of the transaction —
            // same reasoning as OrderStateMachine::transition(): two
            // completions racing each other must never together refund
            // more than the order is actually worth.
            $order = Order::withoutGlobalScope(BranchScope::class)
                ->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();

            $locked = Refund::withoutGlobalScope(BranchScope::class)
                ->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $refund->setRawAttributes($locked->getAttributes(), true);

            $this->assertStatus($refund, Refund::STATUS_APPROVED);
            $this->assertRefundable($order, $refund->amount, excludingRefundId: $refund->id);

            $providerReference = null;
            $rawPayload = null;

            if ($order->payment_method === 'paystack') {
                $originalReference = $order->payments()
                    ->where('provider', 'paystack')
                    ->where('status', 'paid')
                    ->latest('verified_at')
                    ->value('provider_reference');

                if ($originalReference) {
                    $response = $this->paystack->refundTransaction($originalReference, $refund->amount);
                    $providerReference = $response['data']['id'] ?? $response['data']['transaction']['reference'] ?? null;
                    $rawPayload = $response;
                }
            }

            $order->payments()->create([
                'provider' => $order->payment_method,
                'provider_reference' => $providerReference,
                'amount' => $refund->amount,
                'currency' => 'GHS',
                'status' => 'refunded',
                'raw_payload' => $rawPayload,
                'verified_at' => now(),
            ]);

            $refund->update([
                'status' => Refund::STATUS_COMPLETED,
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'provider_reference' => $providerReference,
            ]);

            $this->logEvent($order, $actorType, $actor->id, $shiftId, [
                'action' => 'refund_completed', 'refund_id' => $refund->id, 'amount' => $refund->amount,
            ]);

            return $refund->fresh();
        });
    }

    private function assertRefundable(Order $order, int $amount, ?int $excludingRefundId = null): void
    {
        if ($order->payment_status !== 'paid') {
            throw RefundException::orderNotPaid();
        }

        $completed = Refund::where('order_id', $order->id)
            ->where('status', Refund::STATUS_COMPLETED)
            ->when($excludingRefundId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->sum('amount');

        $remaining = $order->total - $completed;

        if ($amount > $remaining) {
            throw RefundException::amountExceedsRemainingBalance($remaining);
        }
    }

    private function assertStatus(Refund $refund, string $expected): void
    {
        if ($refund->status !== $expected) {
            throw RefundException::wrongStatus($expected, $refund->status);
        }
    }

    /**
     * from_status/to_status stay equal — a refund is a financial event,
     * never a status change (see class docblock) — same pattern as
     * PaymentConfirmationService's momo-confirmation event.
     *
     * @param  array<string, mixed>  $meta
     */
    private function logEvent(Order $order, string $actorType, ?int $actorId, ?int $shiftId, array $meta): void
    {
        $order->events()->create([
            'from_status' => $order->status,
            'to_status' => $order->status,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'shift_id' => $shiftId,
            'meta' => $meta,
        ]);
    }
}
