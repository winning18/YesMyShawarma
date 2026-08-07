<?php

namespace App\Http\Middleware;

use App\Services\Visitors\VisitorSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attached only to the public customer route group (routes/web.php) —
 * never the staff dashboard, POS, or rider routes, so nothing here ever
 * counts a staff member's own dashboard use as a "visit". A year-long
 * cookie so a returning customer isn't miscounted as a brand new visitor
 * a week later; the cookie itself carries no PII, just a random token.
 */
class TrackVisitorSession
{
    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function __construct(private readonly VisitorSessionService $visitors) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(VisitorSessionService::COOKIE);
        $isNewToken = ! $token;
        $token ??= $this->visitors->resolveToken($request);

        $this->visitors->recordVisit($token);

        $response = $next($request);

        if ($isNewToken) {
            $response->headers->setCookie(Cookie::create(
                VisitorSessionService::COOKIE,
                $token,
                time() + self::COOKIE_MINUTES * 60,
                path: '/',
                secure: $request->secure(),
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $response;
    }
}
