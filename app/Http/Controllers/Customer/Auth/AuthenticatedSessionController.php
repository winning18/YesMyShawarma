<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use App\Services\Customers\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.login');
    }

    public function store(LoginRequest $request, CustomerService $customers): RedirectResponse
    {
        $request->authenticate($customers);

        $request->session()->regenerate();

        // Order history by default (owner, 2026-11-11); a customer sent to
        // log in from somewhere specific (e.g. checkout) goes back there.
        return redirect()->intended(route('customer.account.orders'));
    }

    /**
     * session()->regenerate(), not invalidate() — see
     * Auth\AuthenticatedSessionController::destroy()'s own comment. A
     * staff member or rider logged in in the same browser (a plausible
     * "browsing the site as a customer to check something" case) must
     * survive a customer logout here.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->regenerate();

        return redirect('/');
    }
}
