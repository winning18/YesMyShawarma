<?php

namespace App\Policies;

use App\Models\DamageReport;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Filing and reviewing are two different audiences, same shape as refunds
 * (RefundPolicy): staff/riders file, manager/general_manager/owner review
 * as equals (damage_reports.review) with no approval consequence beyond
 * the record itself — see schema.md's Damage reports section.
 */
class DamageReportPolicy
{
    /**
     * The dashboard's Damage Reports page — staff who can file one, or
     * anyone who can review one.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAny(['damage_reports.file', 'damage_reports.review']);
    }

    /**
     * $order given means this is a rider reporting damage on an order
     * they're actually carrying — the same "own assigned order only" shape
     * as OrderPolicy::advanceStatus(), needing no damage_reports.file
     * permission at all (riders don't hold one). $order absent means
     * staff's general, not-order-specific report, which does need it.
     */
    public function create(User $user, ?Order $order = null): bool
    {
        if ($order) {
            return $order->rider_id === $user->id;
        }

        return $user->can('damage_reports.file');
    }

    public function approve(User $user, DamageReport $damageReport): bool
    {
        return $this->checkAtReportBranch($user, $damageReport);
    }

    public function deny(User $user, DamageReport $damageReport): bool
    {
        return $this->checkAtReportBranch($user, $damageReport);
    }

    /**
     * Viewing the photo is also how photo_viewed_at gets set (see
     * DamageReportController::photo()) — gated the same as review, plus
     * the original reporter may always see their own submission back.
     */
    public function viewPhoto(User $user, DamageReport $damageReport): bool
    {
        if ($damageReport->reported_by === $user->id) {
            return true;
        }

        return $this->checkAtReportBranch($user, $damageReport);
    }

    /**
     * Same reasoning as RefundPolicy::checkAtRefundBranch — checked
     * against the report's own branch_id, not whatever branch happens to
     * be current in session.
     */
    private function checkAtReportBranch(User $user, DamageReport $damageReport): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($damageReport->branch_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $user->can('damage_reports.review');
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
