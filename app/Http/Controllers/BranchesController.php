<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\NearestBranchFinder;
use App\Services\Branches\WorkingHoursService;
use App\Services\Cart\CartService;
use App\Services\Customers\CustomerBranchSelection;
use App\Services\Seo\RestaurantSchemaBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'deliveryAreas' => config('delivery_areas.'.$branch->slug, []),
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

    public function select(Request $request, Branch $branch, CustomerBranchSelection $selection, CartService $cart): RedirectResponse
    {
        // The index listing only shows active branches, but that's just a
        // UI filter — nothing stopped this route being hit directly for an
        // inactive branch's id.
        if (! $branch->is_active) {
            return redirect()->route('branches.index')->with('status', 'That branch is no longer available. Please choose another.');
        }

        $selection->set($branch->id);
        $removed = $cart->moveToBranch($branch);

        // Only ever one of two fixed destinations — never a URL taken from
        // the request, so this can't become an open redirect.
        $destination = $request->query('return') === 'checkout' && ! $cart->isEmpty()
            ? route('checkout.show')
            : route('menu.index');

        return redirect($destination)->with('status', $this->switchMessage($branch, $removed));
    }

    /**
     * "Find my nearest branch". JSON for the page's own fetch (the
     * coordinates go in a POST body, kept out of access logs). With
     * apply=1 (the Branches page and branch bar) it also selects that
     * branch and moves the cart; without it (checkout) it only reports, so
     * the page can suggest a switch rather than make one.
     */
    public function nearest(Request $request, NearestBranchFinder $finder, CustomerBranchSelection $selection, CartService $cart): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'apply' => ['boolean'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $result = $finder->find($lat, $lng);

        if (! $result) {
            return response()->json(['message' => __('No branch is available right now.')], 404);
        }

        $branch = $result['branch'];
        $current = ($cart->isEmpty() ? null : Branch::find($cart->branchId())) ?? $selection->current();

        if ($request->boolean('apply')) {
            $selection->set($branch->id, CustomerBranchSelection::SOURCE_NEAREST);
            $removed = $cart->moveToBranch($branch);

            $message = $result['closer_closed']
                ? __(':closed is closed right now, so you\'re ordering from :branch (:km km away).', ['closed' => $result['closer_closed']->name, 'branch' => $branch->name, 'km' => $result['distance_km']])
                : __('You\'re ordering from :branch, your nearest branch (:km km away).', ['branch' => $branch->name, 'km' => $result['distance_km']]);

            session()->flash('status', trim($message.' '.$this->removedMessage($branch, $removed)));
        }

        $currentDistanceKm = $current ? $finder->distanceKm($current, $lat, $lng) : null;

        return response()->json([
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'slug' => $branch->slug],
            'distance_km' => $result['distance_km'],
            'closer_closed' => $result['closer_closed']?->name,
            'current' => $current ? ['id' => $current->id, 'name' => $current->name, 'distance_km' => $currentDistanceKm] : null,
            'suggest_switch' => $current !== null && ! $current->is($branch)
                && $currentDistanceKm - $result['distance_km'] >= NearestBranchFinder::SUGGEST_SWITCH_KM,
            'select_url' => route('branches.pick', $branch),
            'redirect' => route('menu.index'),
        ]);
    }

    /**
     * @param  list<string>  $removed
     */
    private function switchMessage(Branch $branch, array $removed): string
    {
        return trim(__('You\'re ordering from :branch.', ['branch' => $branch->name]).' '.$this->removedMessage($branch, $removed));
    }

    /**
     * @param  list<string>  $removed
     */
    private function removedMessage(Branch $branch, array $removed): string
    {
        if ($removed === []) {
            return '';
        }

        return trans_choice(
            ':items isn\'t available at :branch and was removed from your cart.|:items aren\'t available at :branch and were removed from your cart.',
            count($removed),
            ['items' => collect($removed)->join(', ', ' and '), 'branch' => $branch->name],
        );
    }
}
