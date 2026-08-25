<?php

namespace App\Providers;

use App\Contracts\Notifier;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Cart\CartService;
use App\Services\Notifications\ArkeselNotifier;
use App\Services\Notifications\LogNotifier;
use App\Services\Payments\PaystackClient;
use App\Services\Shifts\ShiftService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaystackClient::class, fn () => new PaystackClient(
            secretKey: config('services.paystack.secret_key'),
            baseUrl: config('services.paystack.base_url'),
        ));

        // Real SMS only once Arkesel is actually configured — local/testing
        // environments fall back to logging, same reasoning as
        // PaystackClient above needing no key to boot.
        $this->app->bind(
            Notifier::class,
            fn () => config('services.arkesel.api_key') ? new ArkeselNotifier : new LogNotifier,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Owner is "implicitly across all branches" (permissions.md) but
        // spatie's teams mode anchors their role row at a single branch (see
        // BranchContext::hasRoleAtAnyBranch) — every other ability check
        // would fail for any order at a different branch. This bypass is
        // the actual mechanism behind that "implicitly".
        Gate::before(function (User $user, string $ability) {
            return app(BranchContext::class)->hasRoleAtAnyBranch($user, 'owner') ? true : null;
        });

        View::composer('layouts.customer', function ($view) {
            $view->with('cartItemCount', app(CartService::class)->count());
        });

        // Rendered on every staff-dashboard page regardless of which
        // controller sent it there — a controller-by-controller prop is
        // how the Dashboard nav link would end up correct on some pages
        // and stale on others. staff.web_orders_check's redirect and this
        // gate independently arrive at the same "no active shift" fact for
        // a different reason (see RedirectStaffToUnacceptedWebOrders).
        View::composer('layouts.navigation', function ($view) {
            $user = Auth::guard('web')->user();
            $context = app(BranchContext::class);

            // Falls back to their own assigned branch when the session
            // branch isn't resolved yet — /profile and a few other routes
            // sit outside the 'branch' middleware group entirely, so on a
            // brand new session where one of those happens to be the very
            // first page visited, BranchContext::id() is still null. Without
            // this, the nav would silently render as if they weren't staff
            // at all until they happened to load a 'branch'-gated page.
            $branchId = $user ? ($context->id() ?? $context->branchIdsFor($user)->first()) : null;
            $role = $branchId ? $context->primaryRoleFor($user, $branchId) : null;
            $isStaff = $role === 'staff';

            $view->with([
                'navIsStaff' => $isStaff,
                'navHasActiveShift' => $isStaff && (bool) app(ShiftService::class)->activeFor($user),
                // Manager's (and general_manager's) "Dashboard" link goes
                // to the business overview (PerformanceController), same
                // as owner's — the Orders nav item is how they reach the
                // live board/POS/shift instead, at whichever branch is
                // currently selected. Owner never gets this item at all
                // (see OrderDashboardController/PosController's redirects).
                'navIsManager' => in_array($role, ['manager', 'general_manager'], true),
            ]);
        });
    }
}
