<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Settings\BusinessProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 10: Help — the staff quick-start for everyone, the
 * owner guide and go-live checklist for those who run the settings.
 */
class HelpPagesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::create(['key' => BusinessProfile::NAME, 'value' => 'Mama Put Kitchen']);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
    }

    private function as(string $role): static
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $this->actingAs($user)->withSession(['current_branch_id' => $this->branch->id]);
    }

    public function test_managers_see_every_guide_written_for_their_business(): void
    {
        $this->as('manager')->get(route('dashboard.help.index'))->assertOk()
            ->assertSee('Staff quick-start')->assertSee('Owner guide')->assertSee('Go-live checklist');

        foreach (['staff', 'owner', 'go-live'] as $guide) {
            $this->as('manager')->get(route('dashboard.help.show', $guide))->assertOk()->assertSee('Mama Put Kitchen')->assertSee('Print');
        }
    }

    public function test_staff_see_only_the_quick_start(): void
    {
        $this->as('staff')->get(route('dashboard.help.index'))->assertOk()->assertSee('Staff quick-start')->assertDontSee('Owner guide');
        $this->as('staff')->get(route('dashboard.help.show', 'owner'))->assertForbidden();
    }

    public function test_guides_leave_out_switched_off_modules(): void
    {
        $this->as('manager')->get(route('dashboard.help.show', 'staff'))->assertSee('Assign to…');

        app(ModuleService::class)->set(ModuleService::DELIVERY, false);
        $this->as('manager')->get(route('dashboard.help.show', 'staff'))->assertDontSee('Assign to…');
    }

    public function test_pages_tell_the_script_this_installs_realtime_server(): void
    {
        config(['broadcasting.connections.reverb.key' => 'pilot-key', 'broadcasting.connections.reverb.options.host' => 'pilot.example.test']);

        $this->as('staff')->get(route('dashboard.help.index'))
            ->assertSee('name="reverb-config"', false)
            ->assertSee('pilot.example.test')
            ->assertSee('pilot-key');
    }
}
