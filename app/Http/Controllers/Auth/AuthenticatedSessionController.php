<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Branches\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * A rider-only account has no business on the staff dashboard — it
     * isn't scoped to "their own" orders the way the rider dashboard is,
     * so letting one through here would leak every order at the branch to
     * them. This only rejects that one specific case (rider role present,
     * no staff-type role anywhere) — someone with no role at all still logs
     * in here same as always and is caught downstream by
     * ResolveCurrentBranch's 403, and someone holding a staff-type role at
     * one branch plus rider at another is unaffected either way.
     */
    public function store(LoginRequest $request, BranchContext $context): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();
        $hasStaffRole = $context->hasRoleAtAnyBranch($user, 'owner')
            || $context->hasRoleAtAnyBranch($user, 'manager')
            || $context->hasRoleAtAnyBranch($user, 'general_manager')
            || $context->hasRoleAtAnyBranch($user, 'staff');
        $riderOnly = ! $hasStaffRole && $context->hasRoleAtAnyBranch($user, 'rider');

        if ($riderOnly) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('This account is a rider account — please use the rider login instead.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
