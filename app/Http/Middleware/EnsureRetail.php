<?php

namespace App\Http\Middleware;

use App\Services\Settings\BusinessType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `retail` on a route: a pharmacy's or shop's part only (product packs, the
 * product import). A restaurant gets 404, as if the page didn't exist
 * (BusinessType; the opposite of `restaurant`).
 */
class EnsureRetail
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app(BusinessType::class)->isRestaurant(), 404);

        return $next($request);
    }
}
