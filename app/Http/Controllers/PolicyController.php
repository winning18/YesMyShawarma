<?php

namespace App\Http\Controllers;

use App\Services\Content\SitePageService;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

/**
 * The four "Policy" footer pages. Their text is written under Website
 * content (SitePageService); a policy nobody has written yet answers 404
 * and isn't linked.
 */
class PolicyController extends Controller
{
    public function terms(SitePageService $pages): View
    {
        return $this->show($pages, 'terms');
    }

    public function refunds(SitePageService $pages): View
    {
        return $this->show($pages, 'refunds');
    }

    public function privacy(SitePageService $pages): View
    {
        return $this->show($pages, 'privacy');
    }

    public function cookies(SitePageService $pages): View
    {
        return $this->show($pages, 'cookies');
    }

    private function show(SitePageService $pages, string $slug): View
    {
        $body = $pages->body($slug);

        abort_unless($body, 404);

        return view('policy.show', [
            'meta' => $pages->meta($slug),
            'heading' => $pages->heading($slug),
            'body' => $body,
        ]);
    }

    /**
     * The Cookie Policy promises visitors can accept, decline, or adjust
     * non-essential cookie use at any time — this is that control.
     * "Decline" also forgets any visitor-analytics cookie and remembered
     * branch (CustomerBranchSelection) already set, so
     * the choice takes effect immediately rather than only for future
     * visits. Nothing here needs a service class: it's a single named
     * cookie, no business logic to extract.
     */
    public function updateCookieConsent(Request $request): RedirectResponse
    {
        $choice = $request->validate(['choice' => ['required', 'in:accept,decline']])['choice'];

        $redirect = back()->withCookie(Cookie::forever('cookie_consent', $choice));

        return $choice === 'decline'
            ? $redirect->withCookie(Cookie::forget('visitor_token'))->withCookie(Cookie::forget(CustomerBranchSelection::COOKIE))
            : $redirect;
    }
}
