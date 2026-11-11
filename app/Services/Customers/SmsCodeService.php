<?php

namespace App\Services\Customers;

use App\Contracts\Notifier;
use App\Exceptions\SmsCodeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Six-digit codes texted to a phone to prove the person holds it — for a
 * customer changing their phone number and for "forgot password" (owner,
 * 2026-11-11). Kept in the cache, not a table: a code only matters for
 * ten minutes, is stored as an HMAC (never the digits themselves), and is
 * deleted the moment it's used or tried too often.
 *
 *  - expires after 10 minutes
 *  - 5 wrong tries and it's gone
 *  - at most 3 texts per number per 10 minutes, per purpose
 *
 * A code is bound to its purpose, and for a phone change to the customer
 * asking, so a code sent for one thing can never be used for another.
 */
class SmsCodeService
{
    public const PHONE_CHANGE = 'phone_change';

    public const PASSWORD_RESET = 'password_reset';

    private const TTL_SECONDS = 600;

    private const MAX_ATTEMPTS = 5;

    private const MAX_SENDS = 3;

    public function __construct(private readonly Notifier $sms) {}

    public function send(string $phone, string $purpose, ?int $customerId = null): void
    {
        $limiterKey = "sms-code-send:{$purpose}:{$phone}";

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_SENDS)) {
            throw SmsCodeException::tooManyRequests((int) ceil(RateLimiter::availableIn($limiterKey) / 60));
        }

        RateLimiter::hit($limiterKey, self::TTL_SECONDS);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($phone, $purpose, $customerId), ['hash' => $this->hash($code), 'attempts' => 0, 'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp()], self::TTL_SECONDS);

        $message = __(':code is your :name code. It expires in 10 minutes. Never share it with anyone.', ['code' => $code, 'name' => config('app.name')]);

        if (! $this->sms->send($phone, $message, ['purpose' => $purpose])) {
            Cache::forget($this->key($phone, $purpose, $customerId));

            throw SmsCodeException::notSent();
        }
    }

    /**
     * Throws on a wrong or expired code; a correct one is used up.
     */
    public function verify(string $phone, string $purpose, string $code, ?int $customerId = null): void
    {
        $key = $this->key($phone, $purpose, $customerId);
        $entry = Cache::get($key);

        if (! $entry) {
            throw SmsCodeException::invalid();
        }

        if (hash_equals($entry['hash'], $this->hash(trim($code)))) {
            Cache::forget($key);

            return;
        }

        $entry['attempts']++;

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            throw SmsCodeException::tooManyAttempts();
        }

        // A wrong try never extends the code's life.
        Cache::put($key, $entry, max(1, $entry['expires_at'] - now()->getTimestamp()));

        throw SmsCodeException::invalid();
    }

    private function key(string $phone, string $purpose, ?int $customerId): string
    {
        return "sms-code:{$purpose}:{$phone}:".($customerId ?? 'none');
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }
}
