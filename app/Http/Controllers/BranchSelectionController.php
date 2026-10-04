<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Shifts\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Picking a branch and starting a shift are two separate decisions for
 * staff now, not one folded step — this controller only ever does the
 * former. A staff member picks (or is auto-resolved to) a branch to see
 * that branch's data everywhere (Order History, Damage Reports, Refunds,
 * Reports — none of which need a shift at all); starting one is required
 * only for the Dashboard and POS, and happens entirely on their own pages
 * (the Dashboard's own forced shiftWidget modal, reached after a branch is
 * already current — see OrderDashboardController's own redirect here,
 * which still fires every time a multi-branch staff member has no active
 * shift, deliberately offering a fresh branch choice before each new
 * shift rather than silently reusing yesterday's).
 */
class BranchSelectionController extends Controller
{
    public function show(Request $request, BranchContext $context, ShiftService $shifts): View|RedirectResponse
    {
        if ($blocked = $this->blockWhileOnShift($request, $context, $shifts)) {
            return $blocked;
        }

        $user = $request->user();

        $view = $this->isRiderOnly($user, $context) ? 'rider.select-branch' : 'branches.select';

        return view($view, [
            'branches' => $context->selectableBranchesFor($user),
            'currentBranchId' => $context->id(),
            // Only the generic picker's own "Not ready? Log out instead"
            // escape cares about this — a staff member landing here (most
            // commonly: multi-branch, about to start a new shift) with
            // truly nowhere else to go shouldn't be stuck with no way out.
            'isStaff' => $context->isStaffOnly($user),
        ]);
    }

    /**
     * Anyone whose current branch has an open shift is locked to it —
     * switching would leave the shift's own branch_id out of sync with
     * users.current_branch_id/session, corrupting which branch's orders,
     * stock and reports the rest of the app attributes to them while the
     * till is still open. This applies regardless of who actually opened
     * that shift — a second staff member who only joined it is just as
     * locked as whoever started it (orders.md's Shifts section), since
     * they're equally part of the branch's open till right now. Riders
     * never hold shifts (availability is login-driven, not shift-driven —
     * see rider-navigation-links.blade.php), so this is a no-op on their
     * path. Ends the moment the shift itself ends, same session or not.
     */
    private function blockWhileOnShift(Request $request, BranchContext $context, ShiftService $shifts): ?RedirectResponse
    {
        $branchId = $context->id();

        if (! $branchId || ! $shifts->activeForBranch($branchId)) {
            return null;
        }

        return redirect($this->guardAwareFallback())
            ->with('status', __('You have an active shift running — end it before switching branches.'));
    }

    /**
     * Must match whichever guard actually authenticated this request — a
     * rider-only account has no 'web' session at all, so always falling
     * back to the staff dashboard() route (behind the plain 'auth' =
     * web-guard middleware) would bounce them to the staff login page,
     * which looks exactly like being logged out even though their rider
     * session is untouched. Checked in the same order auth:web,rider itself
     * resolves a guard (web first, then rider), so a hybrid staff+rider
     * account still lands on their staff dashboard when that's the guard
     * this request authenticated on.
     */
    private function guardAwareFallback(): string
    {
        return Auth::guard('web')->check() ? route('dashboard') : route('rider.dashboard');
    }

    /**
     * Whether to show the rider-branded picker instead of the generic
     * dashboard-chrome one — a rider assigned to a second branch
     * (permissions.md) should never land on staff admin UI they have no
     * access to just to pick which branch they're working from today.
     */
    private function isRiderOnly(User $user, BranchContext $context): bool
    {
        return $context->hasRoleAtAnyBranch($user, 'rider')
            && ! $context->hasRoleAtAnyBranch($user, 'staff')
            && ! $context->hasRoleAtAnyBranch($user, 'manager')
            && ! $context->hasRoleAtAnyBranch($user, 'general_manager')
            && ! $context->hasRoleAtAnyBranch($user, 'owner')
            && ! $context->hasRoleAtAnyBranch($user, 'stock_manager');
    }

    public function store(Request $request, BranchContext $context, ShiftService $shifts): RedirectResponse
    {
        if ($blocked = $this->blockWhileOnShift($request, $context, $shifts)) {
            return $blocked;
        }

        $user = $request->user();
        $availableIds = $context->selectableBranchIdsFor($user);

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($availableIds)],
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);
        $context->setCurrent($branch->id);

        // Breaks what would otherwise be an infinite loop: a first-ever
        // visit to /dashboard with no branch resolved is caught by
        // ResolveCurrentBranch itself (redirect()->guest(), storing plain
        // /dashboard as the intended URL below) — OrderDashboardController
        // ::index() never even runs yet to know "today's new shift" is
        // what's actually going on. Once intended() sends them back to that
        // same bare /dashboard, its own forceShiftStart redirect would
        // normally fire again (no active shift, still multi-branch) and
        // bounce them right back here. This one-request flash flag is the
        // signal that this specific arrival is the direct result of having
        // just picked a branch, so that one check should stand down —
        // any other, later visit to /dashboard has no flash flag left and
        // re-triggers the choice exactly as intended.
        $request->session()->flash('branch_just_confirmed', true);

        // intended() sends the user back to whatever page redirected them
        // here via redirect()->guest() — Order History, Damage Reports, any
        // other branch-gated page, or /dashboard itself. The fallback below
        // only applies when nothing was intended at all, e.g. reaching this
        // page directly rather than via a redirect.
        return Redirect::intended($this->guardAwareFallback());
    }
}
