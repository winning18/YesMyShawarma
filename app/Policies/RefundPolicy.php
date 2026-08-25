<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * owner, manager and general_manager hold identical refund rights
 * (orders.refund — payments.md's Refunds section): approve/deny a
 * staff-submitted request, or refund a customer directly with no approval
 * step at all. Only `staff` is restricted to orders.refund_request —
 * request only, needs one of the other three to approve before
 * completing it. complete() accepts either permission since an approved
 * request is completed by whoever's eligible at that branch, not
 * necessarily the same person who approved it.
 */
class RefundPolicy
{
    /**
     * The sidebar Refunds page (RefundController::index) — everyone who
     * can touch a refund at all, owner/manager/general_manager
     * (orders.refund) and staff (orders.refund_request) alike. Staff get
     * a read-mostly view: RefundController::index() never bypasses
     * BranchScope for them, so it's implicitly their one branch only
     * (same as a plain manager), and the view only ever shows them the
     * "Complete" action on an already-approved request — never
     * Approve/Deny, which stays owner/manager/general_manager only
     * (approve()/deny() below still check orders.refund specifically).
     */
    public function viewAny(User $user): bool
    {
        return $user->canAny(['orders.refund', 'orders.refund_request']);
    }

    public function approve(User $user, Refund $refund): bool
    {
        return $this->checkAtRefundBranch($user, $refund, 'orders.refund');
    }

    public function deny(User $user, Refund $refund): bool
    {
        return $this->checkAtRefundBranch($user, $refund, 'orders.refund');
    }

    public function complete(User $user, Refund $refund): bool
    {
        return $this->checkAtRefundBranch($user, $refund, ['orders.refund_request', 'orders.refund']);
    }

    /**
     * Same reasoning as OrderPolicy::checkAtOrderBranch — checked against
     * the refund's own branch_id, not whatever branch happens to be
     * current in session.
     *
     * @param  string|list<string>  $permission
     */
    private function checkAtRefundBranch(User $user, Refund $refund, string|array $permission): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($refund->branch_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return is_array($permission) ? $user->canAny($permission) : $user->can($permission);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
