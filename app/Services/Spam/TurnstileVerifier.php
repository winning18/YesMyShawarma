<?php

namespace App\Services\Spam;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Passes automatically when no site is configured — same "safe by default,
 * real once a key exists" pattern as PaystackClient/ArkeselNotifier. Until
 * TURNSTILE_SITE_KEY/SECRET_KEY are set, the widget component simply
 * doesn't render (see x-turnstile-widget) and this always returns true, so
 * HoneypotGuard's two checks are the only protection in the meantime —
 * real, just weaker — rather than forms breaking because a feature isn't
 * configured yet.
 */
class TurnstileVerifier
{
    public function isConfigured(): bool
    {
        return (bool) config('services.turnstile.site_key');
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! config('services.turnstile.secret_key')) {
            return true;
        }

        if (! $token) {
            return false;
        }

        try {
            $response = Http::asForm()->post(config('services.turnstile.verify_url'), [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            // Cloudflare being briefly unreachable must never be the
            // reason a genuine customer's message never sends — same
            // reasoning as ArkeselNotifier swallowing its own failures.
            Log::warning('Turnstile verification failed', ['error' => $e->getMessage()]);

            return true;
        }
    }
}
