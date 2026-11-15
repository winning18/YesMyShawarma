<?php

namespace App\Http\Middleware;

use App\Services\Modules\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:market_list` on a route: answers 404 while that module is
 * switched off, as if the page didn't exist (ModuleService). Several
 * modules (`module:freebies,whatsapp_reward`) means any one of them.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $service = app(ModuleService::class);

        abort_unless(collect($modules)->contains(fn (string $module) => $service->enabled($module)), 404);

        return $next($request);
    }
}
