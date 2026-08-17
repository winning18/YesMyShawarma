<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class UserPolicy
{
    /**
     * Whether $actor may move the ($role, $fromBranchId) assignment on
     * $target to a different branch. An owner reaches this trivially —
     * AppServiceProvider's Gate::before grants owner every ability before
     * any policy method runs — so everything below only ever executes for
     * a manager. See permissions.md's users.transfer_branch section for
     * the full set of rules this encodes.
     */
    public function changeBranch(User $actor, User $target, string $role, int $fromBranchId): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        // owner rows aren't transferable through this action at all, and a
        // manager or general_manager row only ever moves at an owner's
        // hand — which, again, never reaches this method. A
        // general_manager outranks a plain manager (see permissions.md),
        // so a manager actor moving one of their assignments is exactly
        // as wrong as moving another manager's.
        if (in_array($role, ['owner', 'manager', 'general_manager'], true)) {
            return false;
        }

        // A manager's authority is scoped to the assignment's own branch,
        // not their ambient session branch — same reasoning as
        // OrderPolicy::checkAtOrderBranch. Holding 'manager' at Branch A
        // says nothing about someone whose current assignment is at B.
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($fromBranchId);

        // spatie's can() caches the roles/permissions relations on the
        // model instance (loadMissing) — not team-aware, so a check made
        // earlier in the request under a different team id would
        // otherwise be silently reused here. See
        // BranchContext::primaryRoleFor for the same fix.
        $actor->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $actor->can('users.transfer_branch');
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $actor->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
