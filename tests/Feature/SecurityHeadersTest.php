<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_response_carries_the_expected_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        // Cloudflare Web Analytics: the injected beacon script and where it reports.
        $this->assertMatchesRegularExpression('/script-src [^;]*https:\/\/static\.cloudflareinsights\.com/', $csp);
        $this->assertMatchesRegularExpression('/connect-src [^;]*https:\/\/cloudflareinsights\.com/', $csp);
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $response = $this->get('https://localhost/');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_on_an_own_website_domain_photos_and_live_updates_from_the_pos_address_are_allowed(): void
    {
        // Yes My Shawarma since the MVP move: website yesmyshawarma.com, POS address in APP_URL.
        config(['app.url' => 'https://yesmyshawarma.pos.solusysdigital.com']);

        $csp = $this->get('http://yesmyshawarma.com/up')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression('#img-src [^;]*https://yesmyshawarma\.pos\.solusysdigital\.com#', $csp);
        $this->assertMatchesRegularExpression('#connect-src [^;]*wss://yesmyshawarma\.pos\.solusysdigital\.com#', $csp);
    }
}
