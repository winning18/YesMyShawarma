<?php

namespace App\Services\Orders;

use App\Events\OrderAssignedToRider;
use App\Models\Order;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Shifts\ShiftService;
use App\Support\SafeBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiderAssignmentService
{
    public function __construct(
        private readonly ShiftService $shifts,
        private readonly BranchContext $branchContext,
    ) {}

    /**
     * Called from OrderStateMachine when a delivery order reaches "ready".
     * Returns the assigned rider, or null if nobody was eligible — that's
     * an expected outcome (order stays "ready" with rider_id null, for
     * manual assignment to pick up), not a failure. See orders.md's rider
     * assignment section.
     *
     * The concurrency risk here isn't two riders racing for one order
     * (there's no rider-initiated action to race) — it's two orders
     * reaching "ready" at once and both picking the *same* rider before
     * either commits. Guarded by locking each candidate rider row in
     * priority order and re-checking eligibility after acquiring the lock,
     * rather than locking the order.
     */
    public function autoAssign(Order $order): ?User
    {
        return DB::transaction(function () use ($order) {
            // shifts carries no role column of its own — staff and riders
            // both start/end shifts through the exact same mechanism
            // (schema.md), so an unfiltered "who's on shift" query here
            // would auto-assign a staff member as the order's "rider" the
            // moment they're on shift and not already carrying an order.
            // Same fix as RiderAvailabilityController's dropdown, applied
            // to the other place this same shift/role gap could bite.
            $riderIds = $this->branchContext->usersWithRole('rider', $order->branch_id)->pluck('id');

            $candidateIds = $this->loggedInRiderIds($riderIds, $order->branch_id);

            if ($candidateIds->isEmpty()) {
                return null;
            }

            foreach ($this->orderByLeastRecentlyAssigned($candidateIds) as $riderId) {
                $rider = User::whereKey($riderId)->lockForUpdate()->first();

                if (! $rider || $this->isCarryingAnOrder($rider)) {
                    continue;
                }

                $this->assignInternal($order, $rider, 'system', null, $this->shifts->activeForBranch($order->branch_id)?->id);

                return $rider;
            }

            return null;
        });
    }

    /**
     * Staff/manager/owner override — see OrderPolicy::assignRider. Doesn't
     * re-check "not already carrying an order": a human overriding the
     * algorithm is trusted to know better in that moment (orders.md).
     */
    public function assign(Order $order, User $rider, User $actor, string $actorRole, ?int $actorShiftId): Order
    {
        return DB::transaction(function () use ($order, $rider, $actor, $actorRole, $actorShiftId) {
            $this->assignInternal($order, $rider, $actorRole, $actor->id, $actorShiftId);

            return $order->fresh();
        });
    }

    /**
     * @param  Collection<int, int>  $candidateIds
     * @return Collection<int, int>
     */
    private function orderByLeastRecentlyAssigned(Collection $candidateIds): Collection
    {
        $lastAssignedAt = Order::whereIn('rider_id', $candidateIds)
            ->whereNotNull('claimed_at')
            ->selectRaw('rider_id, MAX(claimed_at) as last_assigned_at')
            ->groupBy('rider_id')
            ->pluck('last_assigned_at', 'rider_id');

        return $candidateIds->sortBy(
            fn (int $riderId) => $lastAssignedAt->get($riderId) ?? ''
        )->values();
    }

    /**
     * "Available" no longer means "on shift" — starting a shift was never
     * an important part of a rider's day (see permissions.md), so
     * eligibility is decided by two facts instead: is this rider
     * currently AT this branch (`users.current_branch_id`, set by
     * BranchContext::setCurrent() whenever the branch switcher is used —
     * see that method's own docblock for why this can't just be read off
     * a session), and are they actually logged in right now
     * (`users.rider_last_seen_at` within the configured session lifetime —
     * the same window Laravel itself already treats as "still
     * authenticated", not an arbitrary shorter one).
     *
     * Not sessions.user_id: Laravel fills that from whichever guard a
     * request happens to default to, so the rider's own Echo subscription
     * (/broadcasting/auth runs under the default 'web' guard) blanked it a
     * second after their dashboard loaded, a staff login in the same
     * browser overwrote it, and a logged-out rider's old row stayed behind.
     * See markSeen()/markLoggedOut() and orders.md.
     *
     * Public — RiderAvailabilityController's manual-assign dropdown uses
     * this same rule, so the two never drift apart on what "available"
     * means.
     *
     * @param  Collection<int, int>  $riderIds
     * @return Collection<int, int>
     */
    public function loggedInRiderIds(Collection $riderIds, int $branchId): Collection
    {
        if ($riderIds->isEmpty()) {
            return $riderIds;
        }

        return User::whereIn('id', $riderIds)
            ->where('current_branch_id', $branchId)
            ->whereNull('suspended_at')
            ->where('rider_last_seen_at', '>=', now()->subMinutes((int) config('session.lifetime')))
            ->pluck('id');
    }

    /**
     * Available riders at each of $branchIds, for the manual-assign
     * dropdown — each one's current_branch_id is the branch they're
     * available at (loggedInRiderIds() requires it), so the dashboard can
     * list only the riders at an order's own branch. Several branches at
     * once for an owner's all-branches view, which has no single current
     * branch.
     *
     * @param  Collection<int, int>  $branchIds
     * @return Collection<int, User>
     */
    public function availableRiders(Collection $branchIds): Collection
    {
        $ids = $branchIds->flatMap(fn (int $branchId) => $this->loggedInRiderIds(
            $this->branchContext->usersWithRole('rider', $branchId)->pluck('id'),
            $branchId,
        ));

        return User::whereIn('id', $ids->unique())->get();
    }

    /**
     * Every request a rider makes in the rider app (MarkRiderSeen
     * middleware). Written at most once a minute — the rider dashboard
     * refreshes every minute, and a write per request buys nothing over
     * a window measured in hours. A query update, not save(), so it never
     * bumps updated_at or fires model events.
     */
    public function markSeen(User $rider): void
    {
        if ($rider->rider_last_seen_at?->gt(now()->subMinute())) {
            return;
        }

        $rider->rider_last_seen_at = now();
        User::whereKey($rider->id)->toBase()->update(['rider_last_seen_at' => $rider->rider_last_seen_at]);
    }

    /**
     * Rider logout, and suspension (which ends every session) — gone from
     * the dropdown and auto-assignment at once, not up to two hours later.
     */
    public function markLoggedOut(User $rider): void
    {
        $rider->rider_last_seen_at = null;
        User::whereKey($rider->id)->toBase()->update(['rider_last_seen_at' => null]);
    }

    private function isCarryingAnOrder(User $rider): bool
    {
        return Order::where('rider_id', $rider->id)
            ->whereIn('status', ['ready', 'dispatched'])
            ->exists();
    }

    private function assignInternal(Order $order, User $rider, string $actorType, ?int $actorId, ?int $shiftId): void
    {
        $order->rider_id = $rider->id;
        $order->claimed_at = now();
        $order->save();

        $order->events()->create([
            'from_status' => $order->status,
            'to_status' => $order->status,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'shift_id' => $shiftId,
            'meta' => [
                'action' => $actorType === 'system' ? 'auto_assigned' : 'assigned',
                'rider_id' => $rider->id,
            ],
        ]);

        // SafeBroadcast::afterCommit, not a bare DB::afterCommit — see its
        // own docblock for why a broadcast failure must never surface as a
        // failure of the assignment itself.
        $orderId = $order->id;
        $riderId = $rider->id;
        SafeBroadcast::afterCommit(fn () => OrderAssignedToRider::dispatch($orderId, $riderId));
    }
}
