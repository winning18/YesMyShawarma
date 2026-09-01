<?php

namespace App\Services\Visitors;

use App\Models\Order;
use App\Models\PageView;
use App\Models\VisitorSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Anonymous, cookie-based visit tracking for the customer site only —
 * never the staff dashboard, POS, or rider app (TrackVisitorSession is
 * only attached to the public customer route group). No client-side
 * script, no third-party analytics: a first-party cookie plus one row
 * per unique visitor, so "customer conversion" on the Performance page
 * is a real ratio (orders ÷ visits) rather than a fabricated number.
 */
class VisitorSessionService
{
    public const COOKIE = 'visitor_token';

    public function resolveToken(Request $request): string
    {
        return $request->cookie(self::COOKIE) ?? Str::random(40);
    }

    /**
     * Creates the row on a brand new token, otherwise just bumps
     * updated_at ("last seen") — Eloquent's save() always re-touches
     * updated_at even when no other attribute changed. ip_address/
     * user_agent/referrer are Privacy-Policy-listed usage data describing
     * how the visit *started* — set only at creation, never overwritten
     * by a later visit from the same returning token.
     */
    public function recordVisit(string $token, Request $request): VisitorSession
    {
        $session = VisitorSession::firstOrCreate(['token' => $token], [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
        ]);

        $session->touch();

        return $session;
    }

    /**
     * One row per page load, only ever for a GET request (see
     * TrackVisitorSession) — a form POST isn't a "page you view".
     * duration_seconds starts null and is filled in later by the
     * sendBeacon() call in customer.blade.php, if it lands at all.
     */
    public function recordPageView(VisitorSession $session, Request $request): PageView
    {
        return $session->pageViews()->create(['path' => $request->path()]);
    }

    /**
     * branch_id is only known for certain once an order exists — the
     * customer site doesn't scope browsing to a branch upfront (branch is
     * resolved from the delivery zone at checkout, per schema.md). Only
     * the first order counts as the conversion (whereNull('order_id')):
     * a session that already converted shouldn't be re-attributed to a
     * second, unrelated order.
     */
    public function markConverted(Request $request, Order $order): void
    {
        $token = $request->cookie(self::COOKIE);

        if (! $token) {
            return;
        }

        VisitorSession::where('token', $token)
            ->whereNull('order_id')
            ->update(['order_id' => $order->id, 'branch_id' => $order->branch_id]);
    }
}
