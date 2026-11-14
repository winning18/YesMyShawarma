<?php

namespace App\Services\Freebies;

use App\Exceptions\FreebieException;
use App\Models\ComplimentaryOrder;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Orders\Data\ComplimentaryData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Freebies (owner, 2026-11-14): food given free to a customer or staff
 * member, entered at the POS as payment method 'complimentary'. The order
 * goes to the kitchen like any other and uses stock, but the customer pays
 * nothing and it is never sales (Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS).
 * Its menu value is the order's subtotal.
 *
 * Every freebie records who got it and why, then waits for approval with
 * the market list's tier rule: staff freebies by a manager, GM or owner; a
 * manager's or GM's by the owner only; the owner's own approved straight
 * away; nobody approves their own. A rejected freebie stays out of sales,
 * flagged with its value against whoever gave it, for the owner to follow
 * up outside the app. Approve/reject each write an order_events row.
 */
class FreebieService
{
    public const OWNER_APPROVES_ROLES = ['manager', 'general_manager'];

    public function __construct(private readonly BranchContext $context) {}

    /**
     * Called by OrderCreationService inside the order's own transaction.
     */
    public function record(Order $order, ComplimentaryData $data, User $recordedBy, string $recorderRole): ComplimentaryOrder
    {
        $autoApproved = $recorderRole === 'owner';

        return $order->complimentary()->create([
            'recipient_type' => $data->recipientType,
            'recipient_user_id' => $data->recipientType === ComplimentaryOrder::RECIPIENT_STAFF ? $data->recipientUserId : null,
            'recipient_name' => $data->recipientType === ComplimentaryOrder::RECIPIENT_CUSTOMER ? $data->recipientName : null,
            'reason' => $data->reason,
            'status' => $autoApproved ? ComplimentaryOrder::STATUS_APPROVED : ComplimentaryOrder::STATUS_PENDING,
            'recorded_by' => $recordedBy->id,
            'recorder_role' => $recorderRole,
            'reviewed_by' => $autoApproved ? $recordedBy->id : null,
            'reviewed_at' => $autoApproved ? now() : null,
        ]);
    }

    public function approve(ComplimentaryOrder $freebie, User $reviewer, ?string $note = null): ComplimentaryOrder
    {
        return $this->review($freebie, $reviewer, ComplimentaryOrder::STATUS_APPROVED, $note);
    }

    public function reject(ComplimentaryOrder $freebie, User $reviewer, string $reason): ComplimentaryOrder
    {
        return $this->review($freebie, $reviewer, ComplimentaryOrder::STATUS_REJECTED, $reason);
    }

    /**
     * Never your own, and only the owner for a manager's or GM's — the
     * caller still checks freebies.approve at the order's branch.
     */
    public function approverTierAllows(User $user, ComplimentaryOrder $freebie): bool
    {
        if ($user->id === $freebie->recorded_by) {
            return false;
        }

        if (in_array($freebie->recorder_role, self::OWNER_APPROVES_ROLES, true)) {
            return $this->context->hasRoleAtAnyBranch($user, 'owner');
        }

        return true;
    }

    /**
     * Pending freebies $user could approve right now — the sidebar badge.
     */
    public function awaitingApprovalCount(User $user): int
    {
        $isOwner = $this->context->hasRoleAtAnyBranch($user, 'owner');
        $isGm = $this->context->hasRoleAtAnyBranch($user, 'general_manager');

        if (! $isOwner && ! $isGm && ! $user->can('freebies.approve')) {
            return 0;
        }

        $query = ComplimentaryOrder::where('status', ComplimentaryOrder::STATUS_PENDING)
            ->where('recorded_by', '!=', $user->id);

        if ($isOwner) {
            return $query->count();
        }

        $branchIds = $isGm
            ? $this->context->branchIdsForRole($user, 'general_manager')->all()
            : array_filter([$this->context->id()]);

        return $query->whereNotIn('recorder_role', self::OWNER_APPROVES_ROLES)
            ->whereHas('order', fn (Builder $order) => $order->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $branchIds))
            ->count();
    }

    private function review(ComplimentaryOrder $freebie, User $reviewer, string $status, ?string $note): ComplimentaryOrder
    {
        if ($freebie->status !== ComplimentaryOrder::STATUS_PENDING) {
            throw FreebieException::notPending();
        }

        return DB::transaction(function () use ($freebie, $reviewer, $status, $note) {
            $freebie->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $order = $freebie->order;
            $order->events()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'actor_type' => $this->context->primaryRoleFor($reviewer, $order->branch_id),
                'actor_id' => $reviewer->id,
                'meta' => ['action' => $status === ComplimentaryOrder::STATUS_APPROVED ? 'freebie_approved' : 'freebie_rejected', 'note' => $note],
            ]);

            return $freebie->fresh();
        });
    }
}
