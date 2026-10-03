<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Shifts\ShiftService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchSelectionController extends Controller
{
    public function show(Request $request, BranchContext $context, ShiftService $shifts): View|RedirectResponse
    {
        if ($blocked = $this->blockWhileOnShift($request, $context, $shifts)) {
            return $blocked;
        }

        // Only the Menu Editor's "Branch" link sends ?then=menu — it marks
        // this visit so store() knows to land on the Menu page afterwards
        // instead of Dashboard. Cleared on any other visit (e.g. the
        // guest()-redirect bounce from POS/Reports) so a stale flag from an
        // abandoned earlier click can't hijack an unrelated selection.
        if ($request->query('then') === 'menu') {
            $request->session()->put('branch_select_then', 'menu');
        } else {
            $request->session()->forget('branch_select_then');
        }

        $user = $request->user();

        $view = match (true) {
            $this->isRiderOnly($user, $context) => 'rider.select-branch',
            // A staff member has nothing to do with a branch once picked
            // except start a shift there — folding the two into one step
            // means they never see a bare "pick a branch" screen followed
            // immediately by a separate forced "now start your shift"
            // modal for the exact same decision.
            $context->isStaffOnly($user) => 'staff.start-shift',
            default => 'branches.select',
        };

        return view($view, [
            'branches' => $context->selectableBranchesFor($user),
            'currentBranchId' => $context->id(),
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
            // Only ever shown/submitted from staff.start-shift — harmless
            // to accept it here regardless, same as the field being absent
            // from every other picker's form.
            'starting_cash' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);
        $context->setCurrent($branch->id);

        // Picking a branch and starting (or joining, if another staff
        // member already opened one there today) a shift are the same
        // decision for a staff member (staff.start-shift's whole reason to
        // exist) — do both in one request so they land on an
        // already-active-shift dashboard instead of the branch now being
        // set but still having to clear the dashboard's own forced
        // start-shift modal right after.
        if ($context->isStaffOnly($user)) {
            $shifts->start(
                $user,
                $branch,
                isset($validated['starting_cash']) ? Money::toPesewas($validated['starting_cash']) : null,
            );

            return redirect()->route('dashboard');
        }

        if ($request->session()->pull('branch_select_then') === 'menu') {
            return redirect()->route('dashboard.menu-items.index');
        }

        // intended() sends the user back to whatever page redirected them
        // here via redirect()->guest() (POS, or any branch-gated page the
        // ResolveCurrentBranch middleware bounced them from) — the fallback
        // below only applies when nothing specific was intended, e.g. a
        // manager reaching this page via the sidebar's "Switch branch" link
        // rather than a guest()-redirect bounce.
        return Redirect::intended($this->guardAwareFallback());
    }
}
