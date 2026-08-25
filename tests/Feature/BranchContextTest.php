<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchContextTest extends TestCase
{
    use RefreshDatabase;

    private Branch $osu;

    private Branch $eastLegon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->osu = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->eastLegon = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    public function test_single_branch_staff_is_auto_resolved(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->osu);

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertOk();
        $this->assertSame($this->osu->id, session('current_branch_id'));
    }

    public function test_multi_branch_manager_is_redirected_to_branch_selection(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);
        $this->assignRoleAt($manager, 'manager', $this->eastLegon);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertRedirect(route('branches.select'));
    }

    public function test_multi_branch_manager_can_select_a_branch_and_proceed(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);
        $this->assignRoleAt($manager, 'manager', $this->eastLegon);

        $this->actingAs($manager)
            ->post(route('branches.select.store'), ['branch_id' => $this->eastLegon->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($this->eastLegon->id, session('current_branch_id'));

        // Manager's Dashboard is the business overview, not the live board
        // — see OrderDashboardController.
        $this->actingAs($manager)->get('/dashboard')->assertRedirect(route('dashboard.performance'));
        $this->actingAs($manager)->get(route('dashboard.performance'))->assertOk();
    }

    public function test_manager_cannot_select_a_branch_they_have_no_role_at(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);

        $thirdBranch = Branch::create([
            'name' => 'Spintex', 'slug' => 'spintex', 'phone' => '+233200000003', 'address' => 'C',
            'lat' => 5.7, 'lng' => -0.3, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->actingAs($manager)
            ->post(route('branches.select.store'), ['branch_id' => $thirdBranch->id])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_owner_is_never_forced_through_branch_selection(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        // Redirects to the business overview (OrderDashboardController) —
        // a different, expected redirect, not the "forced to pick a
        // branch" one this test actually guards against.
        $response = $this->actingAs($owner)->get('/dashboard');

        $response->assertRedirect(route('dashboard.performance'));
        $this->assertNull(session('current_branch_id'));

        $this->actingAs($owner)->get(route('dashboard.performance'))->assertOk();
    }

    public function test_owner_can_select_a_branch_they_hold_no_explicit_role_at(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        $this->actingAs($owner)
            ->post(route('branches.select.store'), ['branch_id' => $this->eastLegon->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($this->eastLegon->id, session('current_branch_id'));
    }

    public function test_owners_branch_picker_lists_every_branch(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        $this->actingAs($owner)->get(route('branches.select'))
            ->assertOk()
            ->assertSee('Osu')
            ->assertSee('East Legon');
    }

    public function test_user_with_no_branch_role_is_forbidden(): void
    {
        $rogue = User::factory()->create();

        $this->actingAs($rogue)->get('/dashboard')->assertForbidden();
    }
}
