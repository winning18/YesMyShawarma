<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A suspended business account (owner, 2026-11-24): every page shows a
 * neutral "temporarily unavailable", Paystack's confirmations still arrive.
 */
class SuspendedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->artisan('up');

        parent::tearDown();
    }

    public function test_every_page_is_paused_with_the_business_name_and_no_product_name(): void
    {
        app(SettingsService::class)->set('business_name', 'De Boyes Kitchen');

        $this->artisan('down', ['--render' => 'errors::suspended'])->assertSuccessful();

        $this->get('/')->assertStatus(503)->assertSee('De Boyes Kitchen is temporarily unavailable')->assertDontSee('Express POS');
        $this->get('/login')->assertStatus(503);
    }

    public function test_paystack_confirmations_still_arrive_while_suspended(): void
    {
        $this->artisan('down', ['--render' => 'errors::suspended'])->assertSuccessful();

        $response = $this->postJson('/api/webhooks/paystack', ['event' => 'charge.success'], ['x-paystack-signature' => 'bad']);

        $this->assertNotSame(503, $response->status(), 'the webhook is let through (and then checks its own signature)');
    }
}
