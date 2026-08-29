<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * owner, manager and general_manager hold identical review-moderation
 * rights (reviews.moderate — permissions.md). staff and rider hold
 * nothing here at all — moderation is a business-reputation decision, not
 * an operational one.
 */
class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reviews.moderate');
    }

    public function approve(User $user, Review $review): bool
    {
        return $this->checkAtReviewBranch($user, $review);
    }

    public function reject(User $user, Review $review): bool
    {
        return $this->checkAtReviewBranch($user, $review);
    }

    /**
     * Same reasoning as RefundPolicy::checkAtRefundBranch — checked
     * against the review's own branch_id, not whatever branch happens to
     * be current in session.
     */
    private function checkAtReviewBranch(User $user, Review $review): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($review->branch_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $user->can('reviews.moderate');
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
