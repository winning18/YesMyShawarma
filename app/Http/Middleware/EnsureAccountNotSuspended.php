<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a suspended account out on its very next request (owner,
 * 2026-11-10: "signed out immediately"), whichever guard it's on — staff,
 * rider and customer sessions can all be open in one browser. Runs on
 * every web request; a guest pays nothing, a signed-in user's model is
 * already loaded for the request anyway. See AccountSuspensionService.
 */
class EnsureAccountNotSuspended
{
    /** @var array<string, string> guard => where to send them */
    private const LOGIN_ROUTES = ['web' => 'login', 'rider' => 'rider.login', 'customer' => 'customer.login'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        foreach (self::LOGIN_ROUTES as $guard => $loginRoute) {
            $account = Auth::guard($guard)->user();

            if ($account && $account->suspended_at !== null) {
                Auth::guard($guard)->logout();

                $message = $guard === 'customer'
                    ? __('This account is suspended. Please call the branch.')
                    : __('Your account is suspended. Please contact the branch manager.');

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 401);
                }

                return redirect()->route($loginRoute)->withErrors([$guard === 'rider' ? 'login' : ($guard === 'customer' ? 'phone' : 'email') => $message]);
            }
        }

        return $next($request);
    }
}
