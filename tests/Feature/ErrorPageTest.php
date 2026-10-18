<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * /storage/{path} runs without the web middleware (no session), and
     * in production an unsigned request there is answered with a 404 —
     * whose customer layout used to crash reading the cart from a session
     * that doesn't exist, turning every probe for e.g.
     * /storage/logs/laravel.log into a 500.
     */
    public function test_the_404_page_renders_on_a_request_with_no_session(): void
    {
        $request = Request::create('/storage/logs/laravel.log');
        $this->app->instance('request', $request);
        $this->assertFalse($request->hasSession());

        $html = view('errors.404')->render();

        $this->assertNotEmpty($html);
    }

    public function test_an_unsigned_storage_path_is_never_served(): void
    {
        $status = $this->get('/storage/logs/laravel.log')->getStatusCode();

        // 404 in production, 403 elsewhere (Laravel's ServeFile) — never 200, never 500.
        $this->assertContains($status, [403, 404]);
    }

    public function test_the_test_suite_never_reports_to_sentry(): void
    {
        $this->assertNull(config('sentry.dsn'));
    }
}
