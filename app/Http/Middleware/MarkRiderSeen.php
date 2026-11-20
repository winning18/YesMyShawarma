<?php

namespace App\Http\Middleware;

use App\Services\Orders\RiderAssignmentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records that a rider is using the rider app right now
 * (users.rider_last_seen_at) — what makes them "logged in" for
 * auto-assignment and the staff dashboard's assign dropdown
 * (RiderAssignmentService::loggedInRiderIds()). Last on the rider route
 * group, after 'branch' and 'password.change_required': a rider still
 * picking a branch or forced to change their password can't see orders
 * yet, so they don't count as available until they can.
 */
class MarkRiderSeen
{
    public function __construct(private readonly RiderAssignmentService $riders) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($rider = $request->user('rider')) {
            $this->riders->markSeen($rider);
        }

        return $next($request);
    }
}
