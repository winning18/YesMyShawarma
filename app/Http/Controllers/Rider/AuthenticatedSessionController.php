<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rider\LoginRequest;
use App\Services\Branches\BranchContext;
use App\Services\Customers\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('rider.auth.login');
    }

    /**
     * Same `web` guard and credentials store as staff (see CLAUDE.md's
     * identity model — riders are `users`, not a separate table/guard).
     * This form only exists to give riders their own branded entry point,
     * accept email-or-phone login (see LoginRequest), and reject an
     * otherwise-valid login that has no rider role anywhere, rather than
     * silently landing them on an empty dashboard.
     */
    public function store(LoginRequest $request, BranchContext $context, CustomerService $customers): RedirectResponse
    {
        $request->authenticate($customers);

        if (! $context->hasRoleAtAnyBranch($request->user(), 'rider')) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'login' => __('This account does not have rider access.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('rider.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('rider.login');
    }
}
