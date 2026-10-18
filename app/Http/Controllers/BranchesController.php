<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\WorkingHoursService;
use App\Services\Customers\CustomerBranchSelection;
use App\Services\Seo\RestaurantSchemaBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function index(CustomerBranchSelection $selection, WorkingHoursService $workingHours): View
    {
        $branches = Branch::where('is_active', true)->get()
            ->each(fn (Branch $branch) => $this->withStatus($branch, $workingHours));

        return view('branches.index', [
            'branches' => $branches,
            'selectedBranchId' => $selection->id(),
        ]);
    }

    /**
     * A branch's own page (/branches/{slug}) — what Google can show for
     * "shawarma Pokuase" / "shawarma near Ga Odumase", which the shared
     * /branches listing can't target. Same card as the listing, plus the
     * full weekly hours and the branch's Restaurant structured data.
     */
    public function show(Branch $branch, CustomerBranchSelection $selection, WorkingHoursService $workingHours, RestaurantSchemaBuilder $restaurantSchema): View
    {
        abort_unless($branch->is_active, 404);

        $this->withStatus($branch, $workingHours);

        return view('branches.show', [
            'branch' => $branch,
            'weeklyHours' => $workingHours->forBranch($branch->id),
            'otherBranches' => Branch::where('is_active', true)->whereKeyNot($branch->id)->orderBy('name')->get(['id', 'name', 'slug']),
            'selected' => $selection->id() === $branch->id,
            'restaurantSchema' => $restaurantSchema->forBranch($branch),
        ]);
    }

    /**
     * Transient, non-persisted attributes for x-branch-card — the
     * "Accepting orders" badge needs the actual open/closed state, not
     * just the manual is_accepting_orders switch, so customers aren't told
     * a closed branch is "accepting orders" just because nobody's paused
     * it. Only ever approved reviews; only a handful of branches exist, so
     * these small per-branch queries are fine.
     */
    private function withStatus(Branch $branch, WorkingHoursService $workingHours): Branch
    {
        $branch->is_open_now = $workingHours->isOpenNow($branch);
        $branch->next_opening_label = $branch->is_open_now ? null : $workingHours->nextOpening($branch)?->format('l g:ia');
        $branch->todays_hours_label = $workingHours->todayLabel($branch);
        $branch->reviews_count = $branch->approvedReviews()->count();
        $branch->reviews_avg_rating = $branch->approvedReviews()->avg('rating');
        $branch->recent_reviews = $branch->approvedReviews()->latest('moderated_at')->limit(3)->get();

        return $branch;
    }

    public function select(Branch $branch, CustomerBranchSelection $selection): RedirectResponse
    {
        // The index listing only shows active branches, but that's just a
        // UI filter — nothing stopped this route being hit directly for an
        // inactive branch's id.
        if (! $branch->is_active) {
            return redirect()->route('branches.index')->with('status', 'That branch is no longer available. Please choose another.');
        }

        $selection->set($branch->id);

        return redirect()->route('menu.index');
    }
}
