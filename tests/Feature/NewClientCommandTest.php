<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\SettingsService;
use App\Services\Setup\ClientSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            '--no-interaction' => true,
            ...$overrides,
        ];
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
}
