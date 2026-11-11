<?php

namespace App\Policies;

use App\Models\MarketList;
use App\Models\User;
use App\Services\MarketLists\MarketListService;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recording and approving are two audiences, same shape as damage
 * reports. Approval adds MarketListService::approverTierAllows() on top of
 * the permission: never your own list, and only the owner for a
 * manager's or GM's (owner, 2026-11-11). See permissions.md's "Market
 * list" section.
 */
class MarketListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['market_lists.record', 'market_lists.approve']);
    }

    public function create(User $user): bool
    {
        return $user->can('market_lists.record');
    }

    public function view(User $user, MarketList $marketList): bool
    {
        return $marketList->recorded_by === $user->id || $this->canAtListBranch($user, $marketList, 'market_lists.approve');
    }

    /**
     * The recorder while it isn't approved yet, or an approver tidying up
     * a pending list before approving it.
     */
    public function update(User $user, MarketList $marketList): bool
    {
        if (! $marketList->isEditable()) {
            return false;
        }

        if ($marketList->recorded_by === $user->id) {
            return true;
        }

        return $marketList->status === MarketList::STATUS_PENDING && $this->approve($user, $marketList);
    }

    public function approve(User $user, MarketList $marketList): bool
    {
        return $marketList->status === MarketList::STATUS_PENDING
            && app(MarketListService::class)->approverTierAllows($user, $marketList)
            && $this->canAtListBranch($user, $marketList, 'market_lists.approve');
    }

    public function sendBack(User $user, MarketList $marketList): bool
    {
        return $this->approve($user, $marketList);
    }

    public function viewReceipt(User $user, MarketList $marketList): bool
    {
        return $marketList->receipt_path !== null && $this->view($user, $marketList);
    }

    /**
     * Same reasoning as DamageReportPolicy::checkAtReportBranch — checked
     * against the list's own branch, not whatever branch is in session.
     */
    private function canAtListBranch(User $user, MarketList $marketList, string $permission): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($marketList->branch_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $user->can($permission);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
