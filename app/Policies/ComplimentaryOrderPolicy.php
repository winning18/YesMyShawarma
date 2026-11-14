<?php

namespace App\Policies;

use App\Models\ComplimentaryOrder;
use App\Models\User;
use App\Services\Freebies\FreebieService;
use Spatie\Permission\PermissionRegistrar;

/**
 * Approving or rejecting a freebie: freebies.approve at the order's own
 * branch, plus FreebieService::approverTierAllows() (never your own, and
 * only the owner for a manager's or GM's). See permissions.md's "Freebies".
 */
class ComplimentaryOrderPolicy
{
    public function review(User $user, ComplimentaryOrder $freebie): bool
    {
        if ($freebie->status !== ComplimentaryOrder::STATUS_PENDING || ! app(FreebieService::class)->approverTierAllows($user, $freebie)) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($freebie->order->branch_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $user->can('freebies.approve');
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
