<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fed by a sendBeacon() call fired as the visitor leaves a page
 * (customer.blade.php) — fulfils the Privacy Policy's "how long you
 * spend on" each page. No auth: this route is anonymous like the rest of
 * visitor tracking, so the visitor_token cookie itself is the only check
 * that the caller actually owns the page view it's updating.
 */
class PageViewController extends Controller
{
    public function recordDuration(Request $request, PageView $pageView): Response
    {
        if ($pageView->visitorSession->token !== $request->cookie('visitor_token')) {
            return response()->noContent();
        }

        $data = $request->validate([
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        $pageView->update(['duration_seconds' => $data['duration_seconds']]);

        return response()->noContent();
    }
}
