<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeOwner(): User
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        return $owner;
    }

    public function test_owner_can_view_the_settings_page(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('dashboard.settings.index'))
            ->assertOk()
            ->assertSee('YMGS-POS')
            ->assertSee('YMGS-WEB');
    }

    public function test_owner_can_update_the_order_reference_prefixes(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->put(route('dashboard.settings.update'), [
            'order_reference_prefix_pos' => 'shawarma-pos',
            'order_reference_prefix_web' => 'shawarma-web',
        ])->assertRedirect();

        $settings = app(SettingsService::class);
        $this->assertSame('SHAWARMA-POS', $settings->get(SettingsService::ORDER_REFERENCE_PREFIX_POS));
        $this->assertSame('SHAWARMA-WEB', $settings->get(SettingsService::ORDER_REFERENCE_PREFIX_WEB));
    }

    public function test_prefix_rejects_spaces_and_punctuation(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->put(route('dashboard.settings.update'), [
            'order_reference_prefix_pos' => 'YMGS POS!',
            'order_reference_prefix_web' => 'YMGS-WEB',
        ])->assertSessionHasErrors('order_reference_prefix_pos');
    }

    public function test_manager_can_view_the_settings_page(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.settings.index'))->assertOk();
    }

    public function test_general_manager_can_view_the_settings_page(): void
    {
        $gm = User::factory()->create();
        $this->assignRoleAt($gm, 'general_manager', $this->branch);

        $this->actingAs($gm)->get(route('dashboard.settings.index'))->assertOk();
    }

    public function test_staff_cannot_view_the_settings_page(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.settings.index'))->assertForbidden();
    }

    public function test_settings_nav_link_shows_for_owner_and_manager_but_not_staff(): void
    {
        $owner = $this->makeOwner();
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($owner)->get(route('dashboard.settings.index'))
            ->assertSee('href="'.route('dashboard.settings.index').'"', false);

        $this->actingAs($manager)->get(route('dashboard.settings.index'))
            ->assertSee('href="'.route('dashboard.settings.index').'"', false);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertDontSee('href="'.route('dashboard.settings.index').'"', false);
    }
}
