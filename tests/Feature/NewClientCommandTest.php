<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FaqItem;
use App\Models\SitePage;
use App\Models\User;
use App\Services\Content\SitePageService;
use App\Services\Content\WebsiteThemeService;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\SettingsService;
use App\Services\Setup\ClientSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 10: `expresspos:new-client` fills in a fresh install —
 * and refuses to touch one that's already in use.
 */
class NewClientCommandTest extends TestCase
{
    use RefreshDatabase;

    private function commandOptions(array $overrides = []): array
    {
        return [
            '--business' => 'Mama Put Kitchen',
            '--branch' => 'Osu',
            '--branch-phone' => '+233241112222',
            '--address' => 'Oxford Street, Osu',
            '--lat' => '5.556',
            '--lng' => '-0.1969',
            '--owner-name' => 'Akosua Mensah',
            '--owner-email' => 'akosua@example.test',
            '--support-email' => 'support@example.test',
            '--modules' => 'market_list,tax',
            '--primary-colour' => '#0b7a3a',
            '--accent-colour' => '#fcd116',
            '--logo' => $this->logo(),
            '--no-interaction' => true,
            ...$overrides,
        ];
    }

    /** A small real PNG, as the portal would upload. */
    private function logo(): string
    {
        $path = sys_get_temp_dir().'/expresspos-logo-test.png';
        if (! is_file($path)) {
            $image = imagecreatetruecolor(40, 20);
            imagepng($image, $path);
        }

        return $path;
    }

    public function test_it_sets_up_a_fresh_install(): void
    {
        $this->artisan('expresspos:new-client', $this->commandOptions())
            ->expectsOutputToContain('Mama Put Kitchen is set up')
            ->assertSuccessful();

        $this->assertSame('Mama Put Kitchen', app(BusinessProfile::class)->name());
        $this->assertNotNull(app(SettingsService::class)->get(ClientSetupService::SETUP_MARKER), 'marked, so Yes My Shawarma data migrations skip it');
        $branch = Branch::sole();
        $this->assertSame('osu', $branch->slug);
        $this->assertSame($branch->id, app(BusinessProfile::class)->mainBranch()->id);

        $modules = app(ModuleService::class);
        $this->assertTrue($modules->switchedOn('market_list'));
        $this->assertTrue($modules->switchedOn('tax'));
        $this->assertFalse($modules->switchedOn('website'));
        $this->assertFalse($modules->switchedOn('delivery'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        foreach (['akosua@example.test', 'support@example.test'] as $email) {
            $user = User::where('email', $email)->sole();
            $this->assertTrue($user->hasRole('owner'));
            $this->assertTrue((bool) $user->must_change_password);
        }
    }

    public function test_it_refuses_an_install_already_in_use(): void
    {
        $this->artisan('expresspos:new-client', $this->commandOptions())->assertSuccessful();

        $this->artisan('expresspos:new-client', $this->commandOptions(['--business' => 'Someone Else']))
            ->expectsOutputToContain('already has branches')
            ->assertFailed();
        $this->assertSame('Mama Put Kitchen', app(BusinessProfile::class)->name());
    }

    public function test_it_rejects_unknown_modules_and_bad_input_without_changing_anything(): void
    {
        $this->artisan('expresspos:new-client', $this->commandOptions(['--modules' => 'website,loyalty']))
            ->expectsOutputToContain('Unknown module(s): loyalty')
            ->assertFailed();
        $this->artisan('expresspos:new-client', $this->commandOptions(['--owner-email' => 'not-an-email']))->assertFailed();

        $this->assertFalse(Branch::exists());
        $this->assertFalse(User::exists());
    }

    public function test_json_output_for_the_admin_portal(): void
    {
        $this->artisan('expresspos:new-client', $this->commandOptions(['--json' => true]))
            ->expectsOutputToContain('"accounts":[{"role":"owner","email":"akosua@example.test","temporary_password"')
            ->assertSuccessful();

        $this->artisan('expresspos:new-client', $this->commandOptions(['--json' => true]))
            ->expectsOutputToContain('{"ok":false,"error":"This install already has branches')
            ->assertFailed();
    }

    public function test_a_client_with_a_website_starts_with_an_about_page_and_faq_but_no_policies(): void
    {
        $this->artisan('expresspos:new-client', $this->commandOptions(['--modules' => 'website']))->assertSuccessful();

        $this->assertStringContainsString('Welcome to **Mama Put Kitchen**', SitePage::where('slug', 'about')->value('body'));
        $this->assertSame(5, FaqItem::count());
        $this->assertFalse(app(SitePageService::class)->isPublished('terms'), 'policies are written by the business');
    }

    public function test_a_client_without_a_website_gets_no_website_content(): void
    {
        $this->artisan('expresspos:new-client', $this->commandOptions(['--modules' => 'tax']))->assertSuccessful();

        $this->assertSame(0, FaqItem::count());
    }

    public function test_the_brand_is_required_and_set(): void
    {
        Storage::fake('public');

        $this->artisan('expresspos:new-client', $this->commandOptions(['--primary-colour' => 'green']))->assertFailed();
        $this->artisan('expresspos:new-client', $this->commandOptions(['--logo' => '/nowhere.png']))->assertFailed();
        $this->assertFalse(Branch::exists());

        $this->artisan('expresspos:new-client', $this->commandOptions())->assertSuccessful();
        $profile = app(BusinessProfile::class);
        $this->assertSame('#0b7a3a', $profile->colours()['brand_primary']);
        $this->assertSame('#fcd116', $profile->colours()['brand_accent']);
        $this->assertNotNull($profile->logoUrl());

        $this->artisan('expresspos:brand', ['--primary-colour' => '#112233', '--accent-colour' => '#445566'])->assertSuccessful();
        $this->assertSame('#112233', app(BusinessProfile::class)->colours()['brand_primary']);
    }

    public function test_the_website_look_is_classic_unless_chosen(): void
    {
        Storage::fake('public');

        $this->artisan('expresspos:new-client', $this->commandOptions(['--theme' => 'neon']))->assertFailed();
        $this->assertFalse(Branch::exists());

        $this->artisan('expresspos:new-client', $this->commandOptions())->assertSuccessful();
        $this->assertSame('classic', app(SettingsService::class)->get(SettingsService::WEBSITE_THEME));
    }

    public function test_a_chosen_website_look_is_set_at_onboarding(): void
    {
        Storage::fake('public');

        $this->artisan('expresspos:new-client', $this->commandOptions(['--theme' => 'photo']))->assertSuccessful();
        $this->assertSame('photo', app(WebsiteThemeService::class)->chosen());
    }
}
