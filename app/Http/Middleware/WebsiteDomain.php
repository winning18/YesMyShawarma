<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One install, two addresses (owner, 2026-11-24). With an own website
 * domain set (SettingsService::websiteDomain()):
 *
 * - the website domain serves customer pages only: staff pages (sign-in,
 *   POS, dashboard, rider app) go to the POS address;
 * - the POS address (<client>.pos.solusysdigital.com, APP_URL) serves
 *   staff only: its home opens the staff sign-in, and customer pages go
 *   to the website domain.
 *
 * Webhooks, real-time and health checks answer on either. Without an own
 * domain nothing changes.
 */
class WebsiteDomain
{
    /** First path segments that are staff pages. */
    private const STAFF = [
        'dashboard', 'login', 'logout', 'rider', 'forgot-password', 'reset-password', 'verify-email',
        'email', 'confirm-password', 'password', 'profile', 'print',
    ];

    /** First path segments that answer on both addresses. */
    private const EITHER = ['api', 'broadcasting', 'up', 'downloads', 'build', 'fonts', 'storage'];

    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $website = $this->settings->websiteDomain();
        if ($website === null) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $segment = strtolower((string) $request->segment(1));
        // /branches is the customers' branch finder; only /branches/select
        // (staff choosing their branch) is a staff page.
        $isStaff = in_array($segment, self::STAFF, true)
            || ($segment === 'branches' && strtolower((string) $request->segment(2)) === 'select');
        $onWebsite = $host === $website || $host === 'www.'.$website;

        if (in_array($segment, self::EITHER, true)) {
            return $next($request);
        }

        if ($onWebsite && $isStaff) {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri());
        }

        if (! $onWebsite && $request->isMethod('GET') && ! $isStaff) {
            // The POS address's own home is the staff sign-in.
            if ($segment === '') {
                return redirect('/login');
            }

            return redirect()->away('https://'.$website.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
