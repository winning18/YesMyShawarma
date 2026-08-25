<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user created via UserManagementController::store() gets a system-
 * generated temporary password and must_change_password = true — this is
 * what actually enforces "must" rather than a UI nudge they could ignore.
 * Runs alongside 'branch' on both the staff dashboard and rider route
 * groups (see routes/web.php); the force-change route itself and logout
 * are the only escapes, otherwise every request bounces back here.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('password.force-change*', 'logout', 'rider.logout')) {
            // Same mechanism redirect()->guest() uses for guests — lets
            // ForcePasswordChangeController send them on to wherever they
            // were actually headed (staff or rider dashboard) once done,
            // instead of a hardcoded destination.
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('password.force-change');
        }

        return $next($request);
    }
}
