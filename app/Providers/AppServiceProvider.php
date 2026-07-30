<?php

namespace App\Providers;

use App\Contracts\Notifier;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Cart\CartService;
use App\Services\Notifications\LogNotifier;
use App\Services\Payments\PaystackClient;
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

        $this->app->bind(Notifier::class, LogNotifier::class);
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
    }
}
