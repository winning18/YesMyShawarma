<?php

namespace App\Services\Orders;

use App\Events\OrderAssignedToRider;
use App\Models\Order;
use App\Models\Shift;
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

            $candidateIds = Shift::query()
                ->where('branch_id', $order->branch_id)
                ->whereNull('ended_at')
                ->whereIn('user_id', $riderIds)
                ->pluck('user_id');

            if ($candidateIds->isEmpty()) {
                return null;
            }

            foreach ($this->orderByLeastRecentlyAssigned($candidateIds) as $riderId) {
                $rider = User::whereKey($riderId)->lockForUpdate()->first();

                if (! $rider || $this->isCarryingAnOrder($rider)) {
                    continue;
                }

                $this->assignInternal($order, $rider, 'system', null, $this->shifts->activeFor($rider)?->id);

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
