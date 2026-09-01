<?php

namespace App\Services\Spam;

use Illuminate\Http\Request;

/**
 * Two well-established, dependency-free bot signals, combined — either one
 * alone catches most unsophisticated form spam, and together they rarely
 * false-positive a real visitor:
 *
 * 1. A decoy field (x-honeypot) invisible to a real visitor but visible in
 *    the raw HTML a scraping bot reads — anything filled in it is spam.
 * 2. A hidden render timestamp — no human reads a form, decides what to
 *    write, and submits it in under a couple of seconds; a bot scripting
 *    straight from page-load to POST reliably does.
 *
 * A caught submission should look exactly like a successful one to the
 * caller (see ContactController/NewsletterController) — telling a bot it
 * was caught just teaches it what to change next time.
 */
class HoneypotGuard
{
    private const FIELD = 'website';

    private const TIMESTAMP_FIELD = 'form_rendered_at';

    private const MINIMUM_SECONDS = 2;

    public function isSpam(Request $request): bool
    {
        if ($request->filled(self::FIELD)) {
            return true;
        }

        $renderedAt = $request->input(self::TIMESTAMP_FIELD);

        if (! is_numeric($renderedAt)) {
            return true;
        }

        return (time() - (int) $renderedAt) < self::MINIMUM_SECONDS;
    }
}
