<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branchA = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->branchB = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    public function test_a_multi_branch_manager_is_returned_to_the_page_they_tried_to_visit(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);
        $this->assignRoleAt($manager, 'manager', $this->branchB);

        // ResolveCurrentBranch bounces this to branches.select and remembers
        // Reports as the intended destination (redirect()->guest()).
        $this->actingAs($manager)->get(route('dashboard.reports.index'))
            ->assertRedirect(route('branches.select'));

        $this->actingAs($manager)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('dashboard.reports.index'));
    }

    public function test_selecting_a_branch_with_no_intended_page_falls_back_to_dashboard(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);
        $this->assignRoleAt($manager, 'manager', $this->branchB);

        // Visiting the picker directly (e.g. via the branch switcher), not
        // bounced there from anywhere specific — no intended URL stored.
        $this->actingAs($manager)->get(route('branches.select'));

        $this->actingAs($manager)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('dashboard'));
    }

    public function test_selecting_a_branch_from_the_menu_editor_link_lands_on_the_menu(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);
        $this->assignRoleAt($manager, 'manager', $this->branchB);

        $this->actingAs($manager)->get(route('branches.select', ['then' => 'menu']));

        $this->actingAs($manager)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('dashboard.menu-items.index'));
    }

    public function test_the_menu_editor_then_flag_does_not_leak_into_an_unrelated_selection(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);
        $this->assignRoleAt($manager, 'manager', $this->branchB);

        // Visit the Menu Editor's Branch link (sets the then=menu flag)...
        $this->actingAs($manager)->get(route('branches.select', ['then' => 'menu']));

        // ...then get bounced to the picker from a guarded page without
        // submitting. A real browser follows this redirect immediately, so
        // simulate that follow-up GET too — it must clear the stale flag
        // so the pending Reports-style intended() redirect still wins.
        $this->actingAs($manager)->get(route('dashboard.reports.index'))
            ->assertRedirect(route('branches.select'));
        $this->actingAs($manager)->get(route('branches.select'));

        $this->actingAs($manager)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('dashboard.reports.index'));
    }
}
