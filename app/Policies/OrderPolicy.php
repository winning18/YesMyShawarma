<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\Branches\BranchContext;
use Spatie\Permission\PermissionRegistrar;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, 'orders.view');
    }

    public function accept(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, 'orders.accept');
    }

    public function reject(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, 'orders.reject');
    }

    /**
     * Riders additionally may only advance an order they've actually been
     * assigned — orders.md's "riders only see their own orders" rule
     * extends naturally to acting on the order, not just seeing it. A user
     * whose primary role at this branch isn't "rider" (staff/manager/owner)
     * is unrestricted, same as before.
     */
    public function advanceStatus(User $user, Order $order): bool
    {
        if (! $this->checkAtOrderBranch($user, $order, 'orders.advance_status')) {
            return false;
        }

        if (app(BranchContext::class)->primaryRoleFor($user, $order->branch_id) === 'rider') {
            return $order->rider_id === $user->id;
        }

        return true;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, 'orders.void');
    }

    /**
     * Manual assignment (staff/manager/owner) — the fallback path when
     * auto-assignment finds nobody, or a correction is needed. Pickup
     * orders never need a rider (orders.md: "Pickup orders skip the rider
     * entirely"), and once delivered there's nothing left to reassign.
     */
    public function assignRider(User $user, Order $order): bool
    {
        return $order->fulfilment_type === 'delivery'
            && in_array($order->status, ['ready', 'dispatched'], true)
            && $this->checkAtOrderBranch($user, $order, 'orders.assign_rider');
    }

    /**
     * Checks the ability against $order's own branch rather than whatever
     * branch happens to be current in session. Staff/managers only ever see
     * orders at their one resolved branch anyway (BranchScope), but owner's
     * session branch can be null (aggregate view) or a branch they merely
     * switched to for browsing — the check must still be correct against
     * the specific order in front of it, not the ambient session state.
     */
    private function checkAtOrderBranch(User $user, Order $order, string $permission): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($order->branch_id);

        try {
            return $user->can($permission);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
