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
     * Momo transaction IDs are entered by whoever runs the POS terminal —
     * 'orders.create' rather than 'orders.advance_status' since this is
     * payment intake, not order-status progression, and riders (who hold
     * advance_status but never create orders) have no reason to touch it.
     */
    public function confirmMomoPayment(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, 'orders.create');
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
     * A plain staff request needs approval before it can be completed
     * (RefundPolicy::complete) — orders.refund_request is exactly that
     * "may ask" ability. owner/manager/general_manager hold the broader
     * orders.refund instead, which RefundController::store() uses to skip
     * the request entirely and refund directly (payments.md's Refunds
     * section) — either permission is enough to reach this action at all.
     * Eligibility beyond "may this user act at all" (paid, remaining
     * balance) is RefundService's job, not this policy's — a request that
     * turns out ineligible fails loudly there instead of the button
     * silently not appearing.
     */
    public function requestRefund(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, ['orders.refund_request', 'orders.refund']);
    }

    /**
     * Checks the ability against $order's own branch rather than whatever
     * branch happens to be current in session. Staff/managers only ever see
     * orders at their one resolved branch anyway (BranchScope), but owner's
     * session branch can be null (aggregate view) or a branch they merely
     * switched to for browsing — the check must still be correct against
     * the specific order in front of it, not the ambient session state.
     *
     * @param  string|list<string>  $permission  A list is an OR — any one matching is enough.
     */
    private function checkAtOrderBranch(User $user, Order $order, string|array $permission): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($order->branch_id);

        // spatie's can()/hasRole() cache the roles/permissions relations on
        // the model instance (loadMissing) — not team-aware, so a check
        // made earlier in the request under a different team id would
        // otherwise be silently reused here instead of re-querying for
        // this order's own branch. See BranchContext::primaryRoleFor for
        // the same fix.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return is_array($permission) ? $user->canAny($permission) : $user->can($permission);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
