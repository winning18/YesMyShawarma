<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alpine.js's default (non-CSP) build needs 'unsafe-eval' for its
 * expression evaluation, and this app's inline <script> blocks (Blade
 * templates throughout resources/views) aren't nonced, so 'unsafe-inline'
 * is required too — a nonce/hash refactor across every view is a much
 * larger, separate change. This still meaningfully restricts what a
 * successful injection could load or exfiltrate to (no arbitrary
 * third-party script/connect targets), which is the actual threat CSP
 * defends against here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $origin = $request->getSchemeAndHttpHost();
        $wsOrigin = 'wss://'.$request->getHost();

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net",
            "img-src 'self' data: https://*.tile.openstreetmap.org",
            "connect-src 'self' {$origin} {$wsOrigin} https://nominatim.openstreetmap.org",
            "frame-src 'self' https://www.google.com https://challenges.cloudflare.com",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
