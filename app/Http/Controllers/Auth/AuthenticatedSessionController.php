<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Services\Branches\BranchContext;
use App\Services\Modules\ModuleService;
use App\Services\Shifts\CounterService;
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
    public function create(CounterService $counters): View
    {
        // On a counter, the page opens on Staff ID + PIN (step 7b).
        return view('auth.login', ['counter' => $counters->current()]);
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
    /**
     * Staff ID + PIN at a counter (Express POS step 7b): till staff only,
     * only on a device set up as a counter, at that counter's branch. Not
     * remembered — counters are shared devices.
     */
    public function storePin(PinLoginRequest $request, CounterService $counters, BranchContext $context): RedirectResponse
    {
        $counter = $counters->current() ?? abort(404);
        $user = $request->authenticate($counter);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('signed_in_with_pin', true);
        $context->setCurrent((int) $counter->branch_id);

        return redirect()->intended(route('dashboard', absolute: false));
    }

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
                'email' => __('This account is a rider account. Please use the rider login instead.'),
            ]);
        }

        // A pure stock_manager (no other role) holds no orders.* permission
        // at all, so the default /dashboard landing page (Gate::authorize
        // ('viewAny', Order::class) in OrderDashboardController) would 403
        // them the moment they log in — send them to the one section they
        // can actually reach instead.
        $stockManagerOnly = ! $hasStaffRole && ! $riderOnly && $context->hasRoleAtAnyBranch($user, 'stock_manager');

        $request->session()->regenerate();
        $request->session()->forget('signed_in_with_pin');

        // A stock manager also records the market list; with Stock
        // switched off, that's where they start (ModuleService).
        $modules = app(ModuleService::class);
        $stockManagerHome = match (true) {
            $modules->enabled(ModuleService::STOCK) => route('dashboard.stock.index', absolute: false),
            $modules->enabled(ModuleService::MARKET_LIST) => route('dashboard.market-lists.index', absolute: false),
            default => route('dashboard', absolute: false),
        };

        return redirect()->intended($stockManagerOnly ? $stockManagerHome : route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     *
     * session()->regenerate(), not invalidate() — invalidate() flushes the
     * whole session's data, which would also silently log out a rider
     * logged in in the same browser (config/auth.php's 'rider' guard
     * shares this same session, by design, so the two can coexist).
     * regenerate() still rotates the session ID and CSRF token — the part
     * that actually matters for logout hygiene — without touching data
     * that belongs to a different guard's own login key.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->regenerate();
        $request->session()->forget('signed_in_with_pin');

        return redirect()->route('login');
    }
}
