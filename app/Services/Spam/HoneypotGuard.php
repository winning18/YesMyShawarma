<?php

namespace App\Services\Spam;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Two well-established, dependency-free bot signals, combined — either one
 * alone catches most unsophisticated form spam, and together they rarely
 * false-positive a real visitor:
 *
 * 1. A decoy field (x-honeypot) invisible to a real visitor but visible in
 *    the raw HTML a scraping bot reads — anything filled in it is spam.
 * 2. A hidden render timestamp — no human reads a form, decides what to
 *    write, and submits it in under a couple of seconds; a bot scripting
 *    straight from page-load to POST reliably does. Encrypted (stamp()),
 *    so it can't be forged: a plain number let a bot send any old time
 *    and skip the wait entirely (Nov 2026 audit).
 *
 * Plus tooManyLinks() for free-text fields: spam is stuffed with links, a
 * customer's message to the restaurant almost never is.
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

    /** Links allowed in a free-text message before it's treated as spam. */
    public const MAX_LINKS = 1;

    /**
     * The hidden form_rendered_at value x-honeypot renders.
     */
    public static function stamp(?int $time = null): string
    {
        return Crypt::encryptString((string) ($time ?? time()));
    }

    public function isSpam(Request $request): bool
    {
        if ($request->filled(self::FIELD)) {
            return true;
        }

        try {
            $renderedAt = Crypt::decryptString((string) $request->input(self::TIMESTAMP_FIELD));
        } catch (DecryptException) {
            return true;
        }

        if (! ctype_digit($renderedAt)) {
            return true;
        }

        return (time() - (int) $renderedAt) < self::MINIMUM_SECONDS;
    }

    public function tooManyLinks(?string $text): bool
    {
        return preg_match_all('#https?://|www\.|\b[a-z0-9-]+\.(?:com|net|org|xyz|top|ru|info|biz|io|co|site|online)\b#i', (string) $text) > self::MAX_LINKS;
    }
}
