<?php

namespace Tests\Feature;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
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

    public function test_the_text_input_component_renders_without_shared_form_errors(): void
    {
        // Outside a web request (console, queued mail) nothing shares $errors.
        $html = Blade::render('<x-text-input name="email" />');

        $this->assertStringContainsString('name="email"', $html);
    }

    public function test_the_test_suite_never_reports_to_sentry(): void
    {
        $this->assertNull(config('sentry.dsn'));
    }

    /**
     * The deploy's maintenance page (`artisan down --render="errors::503"`)
     * is pre-rendered while code and database are being swapped, so it
     * must stand alone: no layout, no database, nothing business-specific.
     */
    public function test_the_maintenance_page_needs_no_database_and_no_business_data(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        // Same as `artisan down --render` (DownCommand::prerenderView()).
        (new RegisterErrorViewPaths)();
        $html = view('errors::503')->render();

        $this->assertSame(0, $queries);
        $this->assertStringContainsString('Back in a minute', $html);
        $this->assertStringContainsString('noindex', $html);
        $this->assertStringNotContainsString('Express POS', $html);
        $this->assertStringNotContainsString(config('app.name'), $html);
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
