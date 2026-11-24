<?php

namespace App\Services\Branches;

use App\Models\Branch;

/**
 * What a customer-facing branch card (x-branch-card) shows beyond the
 * branch itself: open now or when it next opens, today's hours, and its
 * reviews. Shared by the Branches page, the Contact page and the One-page
 * website theme.
 */
class BranchStatusService
{
    public function __construct(private readonly WorkingHoursService $workingHours) {}

    public function withStatus(Branch $branch): Branch
    {
        $branch->is_open_now = $this->workingHours->isOpenNow($branch);
        $branch->next_opening_label = $branch->is_open_now ? null : $this->workingHours->nextOpening($branch)?->format('l g:ia');
        $branch->todays_hours_label = $this->workingHours->todayLabel($branch);
        $branch->reviews_count = $branch->approvedReviews()->count();
        $branch->reviews_avg_rating = $branch->approvedReviews()->avg('rating');
        $branch->recent_reviews = $branch->approvedReviews()->latest('moderated_at')->limit(3)->get();

        return $branch;
    }
}
