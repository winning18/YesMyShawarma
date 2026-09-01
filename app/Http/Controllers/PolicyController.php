<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

/**
 * The four static "Policy" footer pages — grouped under one controller
 * since each is a plain content view with no dynamic data, same as
 * FaqController/AboutController's simplicity, just four of them under
 * one footer nav group rather than four near-empty controller classes.
 */
class PolicyController extends Controller
{
    public function terms(): View
    {
        return view('policy.terms');
    }

    public function refunds(): View
    {
        return view('policy.refunds');
    }

    public function privacy(): View
    {
        return view('policy.privacy');
    }

    public function cookies(): View
    {
        return view('policy.cookies');
    }

    /**
     * The Cookie Policy promises visitors can accept, decline, or adjust
     * non-essential cookie use at any time — this is that control.
     * "Decline" also forgets any visitor-analytics cookie already set, so
     * the choice takes effect immediately rather than only for future
     * visits. Nothing here needs a service class: it's a single named
     * cookie, no business logic to extract.
     */
    public function updateCookieConsent(Request $request): RedirectResponse
    {
        $choice = $request->validate(['choice' => ['required', 'in:accept,decline']])['choice'];

        $redirect = back()->withCookie(Cookie::forever('cookie_consent', $choice));

        return $choice === 'decline'
            ? $redirect->withCookie(Cookie::forget('visitor_token'))
            : $redirect;
    }
}
