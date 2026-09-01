<?php

namespace Tests\Feature;

use App\Services\Spam\HoneypotGuard;
use App\Services\Spam\TurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpamGuardTest extends TestCase
{
    private function request(array $data): Request
    {
        return Request::create('/contact', 'POST', $data);
    }

    public function test_a_filled_honeypot_field_is_spam(): void
    {
        $guard = app(HoneypotGuard::class);

        $this->assertTrue($guard->isSpam($this->request([
            'website' => 'http://spam.example',
            'form_rendered_at' => now()->subSeconds(5)->timestamp,
        ])));
    }

    public function test_submitting_too_soon_after_render_is_spam(): void
    {
        $guard = app(HoneypotGuard::class);

        $this->assertTrue($guard->isSpam($this->request([
            'form_rendered_at' => now()->timestamp,
        ])));
    }

    public function test_a_missing_or_non_numeric_timestamp_is_spam(): void
    {
        $guard = app(HoneypotGuard::class);

        $this->assertTrue($guard->isSpam($this->request([])));
        $this->assertTrue($guard->isSpam($this->request(['form_rendered_at' => 'not-a-number'])));
    }

    public function test_a_real_submission_passes(): void
    {
        $guard = app(HoneypotGuard::class);

        $this->assertFalse($guard->isSpam($this->request([
            'form_rendered_at' => now()->subSeconds(5)->timestamp,
        ])));
    }

    public function test_turnstile_passes_automatically_when_not_configured(): void
    {
        config(['services.turnstile.secret_key' => null]);

        $this->assertTrue(app(TurnstileVerifier::class)->verify(null, '127.0.0.1'));
    }

    public function test_turnstile_rejects_a_missing_token_once_configured(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);

        $this->assertFalse(app(TurnstileVerifier::class)->verify(null, '127.0.0.1'));
    }

    public function test_turnstile_verifies_the_token_against_cloudflare_once_configured(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->assertTrue(app(TurnstileVerifier::class)->verify('a-real-token', '127.0.0.1'));

        Http::assertSent(fn ($request) => $request['secret'] === 'test-secret' && $request['response'] === 'a-real-token');
    }

    public function test_turnstile_fails_closed_on_a_rejected_token(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->assertFalse(app(TurnstileVerifier::class)->verify('a-bad-token', '127.0.0.1'));
    }

    public function test_turnstile_fails_open_when_cloudflare_is_unreachable(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $this->assertTrue(app(TurnstileVerifier::class)->verify('a-token', '127.0.0.1'));
    }
}
