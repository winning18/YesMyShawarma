<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsService;
use App\Support\CustomerUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A restaurant's own website domain (owner, 2026-11-24): customers on the
 * website domain, staff on the POS address, each sent to the right one.
 */
class WebsiteDomainTest extends TestCase
{
    use RefreshDatabase;

    private function ownDomain(): void
    {
        config(['app.url' => 'https://deboyes.pos.solusysdigital.com']);
        app(SettingsService::class)->set(SettingsService::WEBSITE_DOMAIN, 'deboyeskitchen.com');
    }

    public function test_without_an_own_domain_nothing_changes(): void
    {
        $this->get('http://deboyes.pos.solusysdigital.com/contact')->assertOk();
        $this->assertSame(url('/track/abc'), CustomerUrl::route('tracking.show', 'abc'));
    }

    public function test_customer_pages_on_the_pos_address_go_to_the_website(): void
    {
        $this->ownDomain();

        $this->get('http://deboyes.pos.solusysdigital.com/menu?category=wraps')
            ->assertRedirect('https://deboyeskitchen.com/menu?category=wraps')->assertStatus(301);
        $this->get('http://deboyes.pos.solusysdigital.com/')->assertRedirect('/login');
        $this->get('http://deboyes.pos.solusysdigital.com/login')->assertOk();
    }

    public function test_staff_pages_on_the_website_go_to_the_pos_address(): void
    {
        $this->ownDomain();

        $this->get('http://deboyeskitchen.com/login')->assertRedirect('https://deboyes.pos.solusysdigital.com/login');
        $this->get('http://www.deboyeskitchen.com/dashboard/pos')->assertRedirect('https://deboyes.pos.solusysdigital.com/dashboard/pos');
        $this->get('http://deboyeskitchen.com/contact')->assertOk();
    }

    public function test_customer_links_use_the_website_domain(): void
    {
        $this->ownDomain();

        $this->assertSame('https://deboyeskitchen.com/track/abc', CustomerUrl::route('tracking.show', 'abc'));
    }

    public function test_the_command_sets_and_clears_the_domain_but_never_a_solusys_address(): void
    {
        config(['app.url' => 'https://deboyes.pos.solusysdigital.com']);

        $this->artisan('expresspos:website-domain', ['domain' => 'www.DeBoyesKitchen.com'])->assertSuccessful();
        $this->assertSame('deboyeskitchen.com', app(SettingsService::class)->websiteDomain());

        $this->artisan('expresspos:website-domain', ['domain' => 'shop.solusysdigital.com'])->assertFailed();
        $this->artisan('expresspos:website-domain', ['--clear' => true])->assertSuccessful();
        $this->assertNull(app(SettingsService::class)->websiteDomain());
    }
}
