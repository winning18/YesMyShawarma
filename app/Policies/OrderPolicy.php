<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
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

    public function advanceStatus(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, 'orders.advance_status');
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->checkAtOrderBranch($user, $order, 'orders.void');
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
