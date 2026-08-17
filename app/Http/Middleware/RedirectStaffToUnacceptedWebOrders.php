<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\Branches\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Immediately staff logs in for the day, direct them to the web orders
 * section if any are unaccepted or arrived before regular hours." A 'paid'
 * web order that's still sitting there by the time anyone logs in already
 * satisfies both halves of that — nothing auto-transitions a 'paid' order,
 * so any unaccepted one has necessarily been waiting since before someone
 * started checking (see .claude/rules/orders.md's escalation section).
 *
 * Runs once per session, not on every request — otherwise a staff member
 * who's already cleared the backlog would get yanked back to the Orders
 * dashboard the next time they try to use POS mid-shift, which is exactly
 * the kind of nag this must not become.
 */
class RedirectStaffToUnacceptedWebOrders
{
    public function __construct(private readonly BranchContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('checked_unaccepted_web_orders')) {
            return $next($request);
        }

        // Only a real full-page navigation counts as "the moment staff logs
        // in" — the dashboard's own polling, order actions, and the shift
        // widget all run as AJAX/JSON and must never get redirected out
        // from under themselves, nor consume the once-per-session check
        // before an actual page load does.
        if (! $request->isMethod('GET') || $request->expectsJson()) {
            return $next($request);
        }

        $request->session()->put('checked_unaccepted_web_orders', true);

        if ($request->routeIs('dashboard', 'shift.*', 'logout')) {
            return $next($request);
        }

        $branchId = $this->context->id();
        $user = $request->user();

        if ($branchId && $this->context->primaryRoleFor($user, $branchId) === 'staff') {
            $hasUnacceptedWebOrders = Order::where('channel', 'web')
                ->where('status', 'paid')
                ->exists();

            if ($hasUnacceptedWebOrders) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
