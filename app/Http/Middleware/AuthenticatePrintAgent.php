<?php

namespace App\Http\Middleware;

use App\Services\Printing\PrintAgentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Express POS Print Agent signs its requests with its secret as a
 * Bearer token (step 8b). Every request also counts as "seen".
 */
class AuthenticatePrintAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $agents = app(PrintAgentService::class);
        $agent = $agents->authenticate($request->bearerToken());

        if (! $agent) {
            return response()->json(['message' => 'This print agent is not connected. Connect it again from the Printers page.'], 401);
        }

        $agents->seen($agent);
        $request->attributes->set('printAgent', $agent);

        return $next($request);
    }
}
