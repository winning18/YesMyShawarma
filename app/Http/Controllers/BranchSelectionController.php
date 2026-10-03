<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Shifts\ShiftService;
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
        if ($blocked = $this->blockWhileOnShift($request, $shifts)) {
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

        $view = $this->isRiderOnly($request->user(), $context) ? 'rider.select-branch' : 'branches.select';

        return view($view, [
            'branches' => $context->selectableBranchesFor($request->user()),
            'currentBranchId' => $context->id(),
        ]);
    }

    /**
     * Whoever's running an open shift is locked onto that shift's branch —
     * switching would leave the shift's own branch_id out of sync with
     * users.current_branch_id/session, corrupting which branch's orders,
     * stock and reports the rest of the app attributes to them while the
     * till is still open. Riders never hold shifts (availability is
     * login-driven, not shift-driven — see rider-navigation-links.blade.php),
     * so activeFor() is always null for them and this is a no-op on their
     * path. Ends the moment the shift itself ends, same session or not —
     * there's no separate "new login" unlock, since starting a new shift
     * is only ever possible once the old one has already ended anyway
     * (ShiftService::start()'s own alreadyOnShift() guard).
     */
    private function blockWhileOnShift(Request $request, ShiftService $shifts): ?RedirectResponse
    {
        if (! $shifts->activeFor($request->user())) {
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
        if ($blocked = $this->blockWhileOnShift($request, $shifts)) {
            return $blocked;
        }

        $availableIds = $context->selectableBranchIdsFor($request->user());

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($availableIds)],
        ]);

        $context->setCurrent((int) $validated['branch_id']);

        if ($request->session()->pull('branch_select_then') === 'menu') {
            return redirect()->route('dashboard.menu-items.index');
        }

        // intended() sends the user back to whatever page redirected them
        // here via redirect()->guest() (POS, or any branch-gated page the
        // ResolveCurrentBranch middleware bounced them from) — the fallback
        // below only applies when nothing specific was intended, e.g. a
        // rider reaching this page via the sidebar's "Switch branch" link
        // rather than a guest()-redirect bounce.
        return Redirect::intended($this->guardAwareFallback());
    }
}
