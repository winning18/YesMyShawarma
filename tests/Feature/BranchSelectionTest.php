<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Shifts\ShiftService;
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

    public function test_selecting_a_branch_persists_it_to_the_user_row(): void
    {
        // Not just the session — RiderAssignmentService needs to read
        // "which branch is this rider at" from outside their own request,
        // where there's no session to read (see BranchContext::setCurrent).
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);
        $this->assignRoleAt($manager, 'manager', $this->branchB);

        $this->actingAs($manager)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchB->id]);

        $this->assertSame($this->branchB->id, $manager->fresh()->current_branch_id);
    }

    public function test_a_rider_switching_branches_with_no_intended_page_falls_back_to_the_rider_dashboard(): void
    {
        // Regression: the rider sidebar's "Switch branch" link visits the
        // picker directly (no redirect()->guest() bounce), so there's no
        // intended URL — the old fallback of route('dashboard') sent a
        // rider-only account at the staff (web-guard) dashboard, which
        // bounced them to the staff login page and looked like a logout.
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);
        $this->assignRoleAt($rider, 'rider', $this->branchB);

        $this->actingAs($rider, 'rider')->get(route('branches.select'));

        $this->actingAs($rider, 'rider')
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('rider.dashboard'));
    }

    public function test_a_rider_assigned_to_two_branches_sees_the_rider_branded_picker(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);
        $this->assignRoleAt($rider, 'rider', $this->branchB);

        $this->actingAs($rider, 'rider')->get(route('branches.select'))
            ->assertViewIs('rider.select-branch');
    }

    public function test_the_riders_current_branch_is_preselected_and_labelled(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);
        $this->assignRoleAt($rider, 'rider', $this->branchB);

        // Already serving branch A this session (e.g. selected earlier,
        // or the single-branch auto-resolve before a second role was added).
        $this->actingAs($rider, 'rider')
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id]);

        $response = $this->actingAs($rider, 'rider')->get(route('branches.select'));

        $response->assertViewHas('currentBranchId', $this->branchA->id);
        $response->assertSee('Currently serving');

        // Only branch A's radio is checked, not branch B's.
        $response->assertSee('value="'.$this->branchA->id.'" checked', false);
        $response->assertDontSee('value="'.$this->branchB->id.'" checked', false);
    }

    public function test_a_manager_sees_the_generic_picker_not_the_rider_one(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);
        $this->assignRoleAt($manager, 'manager', $this->branchB);

        $this->actingAs($manager)->get(route('branches.select'))
            ->assertViewIs('branches.select');
    }

    public function test_a_staff_only_account_sees_the_generic_picker_not_a_combined_one(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        $this->assignRoleAt($staff, 'staff', $this->branchB);

        $this->actingAs($staff)->get(route('branches.select'))
            ->assertViewIs('branches.select')
            ->assertSee(__('Not ready? Log out instead.'));
    }

    public function test_submitting_the_picker_selects_the_branch_but_never_starts_a_shift(): void
    {
        // Picking a branch and starting a shift are two separate decisions
        // now — the former just sets which branch's data staff sees
        // everywhere; the latter only ever happens via the Dashboard's own
        // forced shift-start modal, once a branch is already current.
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        $this->assignRoleAt($staff, 'staff', $this->branchB);

        $this->actingAs($staff)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($this->branchA->id, $staff->fresh()->current_branch_id);
        $this->assertNull(app(ShiftService::class)->activeForBranch($this->branchA->id));
    }

    public function test_a_multi_branch_staff_member_can_reach_order_history_directly_with_no_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        $this->assignRoleAt($staff, 'staff', $this->branchB);

        // ResolveCurrentBranch bounces this to the picker and remembers
        // Order History as the intended destination (redirect()->guest()).
        $this->actingAs($staff)->get(route('dashboard.orders.history'))
            ->assertRedirect(route('branches.select'));

        $this->actingAs($staff)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('dashboard.orders.history'));

        $this->assertNull(app(ShiftService::class)->activeForBranch($this->branchA->id));
    }

    public function test_picking_a_branch_from_the_dashboards_own_redirect_does_not_loop_back_to_the_picker(): void
    {
        // Regression: a first-ever visit to /dashboard with no branch
        // resolved is caught by ResolveCurrentBranch itself (redirect()->
        // guest(), storing the bare /dashboard URL as intended) —
        // OrderDashboardController::index() never runs yet to know "no
        // active shift" is actually the reason. Once intended() sends them
        // back to that same /dashboard, its own forceShiftStart redirect
        // would otherwise fire again (still no shift, still multi-branch)
        // and bounce them straight back to the picker in an infinite loop.
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        $this->assignRoleAt($staff, 'staff', $this->branchB);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertRedirect(route('branches.select'));

        $this->actingAs($staff)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id])
            ->assertRedirect(route('dashboard'));

        // The real assertion: landing on /dashboard a second time (as a
        // browser actually following that redirect would) must show the
        // board, not bounce back to the picker again.
        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    }

    public function test_a_hybrid_staff_and_manager_account_sees_the_generic_picker_not_the_combined_one(): void
    {
        $hybrid = User::factory()->create();
        $this->assignRoleAt($hybrid, 'staff', $this->branchA);
        $this->assignRoleAt($hybrid, 'manager', $this->branchB);

        $this->actingAs($hybrid)->get(route('branches.select'))
            ->assertViewIs('branches.select');

        // Submitting it must not start a shift on their behalf either.
        $this->actingAs($hybrid)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id]);

        $this->assertNull(app(ShiftService::class)->activeForBranch($this->branchA->id));
    }

    public function test_a_staff_member_cannot_switch_branches_while_on_an_active_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        $this->assignRoleAt($staff, 'staff', $this->branchB);

        $this->actingAs($staff)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id]);
        app(ShiftService::class)->start($staff, $this->branchA);

        // The picker itself is skipped entirely — nothing to pick, it's locked.
        $this->actingAs($staff)->get(route('branches.select'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        // Defence in depth: a direct POST is rejected the same way, and the
        // branch actually stays put.
        $this->actingAs($staff)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchB->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($this->branchA->id, $staff->fresh()->current_branch_id);
    }

    public function test_a_staff_member_can_switch_branches_again_once_the_shift_ends(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        $this->assignRoleAt($staff, 'staff', $this->branchB);

        $this->actingAs($staff)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchA->id]);

        $shifts = app(ShiftService::class);
        $shifts->start($staff, $this->branchA);
        $shifts->end($shifts->activeForBranch($this->branchA->id), $staff, totalSales: 0, systemSales: 0, noExpenses: true);

        $this->actingAs($staff)
            ->post(route('branches.select.store'), ['branch_id' => $this->branchB->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($this->branchB->id, $staff->fresh()->current_branch_id);
    }
}
