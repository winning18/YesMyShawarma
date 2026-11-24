<?php

namespace App\Http\Middleware;

use App\Services\Content\WebsiteThemeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A preview link from Settings → Website content → Website look
 * (?theme_preview=<theme>&until=…&sig=…) shows the site in that theme for
 * this browser only; ?theme_preview=off ends it. Either way the visitor
 * lands on the same page without the preview details in its address.
 */
class PreviewWebsiteTheme
{
    public function __construct(private readonly WebsiteThemeService $themes) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $this->themes->handlePreviewRequest()) {
            $query = collect($request->query())->except(['theme_preview', 'until', 'sig'])->all();

            return redirect($request->url().($query === [] ? '' : '?'.http_build_query($query)));
        }

        return $next($request);
    }
}
