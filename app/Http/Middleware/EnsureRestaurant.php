<?php

namespace App\Http\Middleware;

use App\Services\Settings\BusinessType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `restaurant` on a route: a restaurant's part only (Modifiers, the menu
 * Time table). A pharmacy or shop gets 404, as if the page didn't exist
 * (BusinessType, R1).
 */
class EnsureRestaurant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(BusinessType::class)->isRestaurant(), 404);

        return $next($request);
    }
}
