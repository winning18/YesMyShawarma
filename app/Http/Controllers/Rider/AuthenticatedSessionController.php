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
     * Same `users` table/credentials as staff, but its own `rider` guard
     * (see config/auth.php's own comment) — this form only exists to give
     * riders their own branded entry point, accept email-or-phone login
     * (see LoginRequest), and reject an otherwise-valid login that has no
     * rider role anywhere, rather than silently landing them on an empty
     * dashboard.
     */
    public function store(LoginRequest $request, BranchContext $context, CustomerService $customers): RedirectResponse
    {
        $request->authenticate($customers);

        // $request->user('rider'), not the bare $request->user() — that
        // resolves the default ('web') guard, which nobody just
        // authenticated against here.
        if (! $context->hasRoleAtAnyBranch($request->user('rider'), 'rider')) {
            Auth::guard('rider')->logout();

            throw ValidationException::withMessages([
                'login' => __('This account does not have rider access.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('rider.dashboard', absolute: false));
    }

    /**
     * session()->regenerate(), not invalidate() — see
     * Auth\AuthenticatedSessionController::destroy()'s own comment. A
     * staff member logged in in the same browser must survive a rider
     * logout (and vice versa) — that coexistence is this guard's whole
     * reason for existing.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('rider')->logout();

        $request->session()->regenerate();

        return redirect()->route('rider.login');
    }
}
