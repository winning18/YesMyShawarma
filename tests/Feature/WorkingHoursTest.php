<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchWorkingHour;
use App\Models\User;
use App\Services\Branches\WorkingHoursService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkingHoursTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    public function test_staff_cannot_view_working_hours(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->osu);

        $this->actingAs($staff)->get(route('dashboard.working-hours.index'))->assertForbidden();
    }

    public function test_rider_cannot_view_working_hours(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->osu);

        $this->actingAs($rider)->get(route('dashboard.working-hours.index'))->assertForbidden();
    }

    public function test_working_hours_sidebar_link_hidden_from_staff(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->osu);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('dashboard.working-hours.index').'"', false);
    }

    public function test_working_hours_sidebar_link_visible_to_manager(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);

        $this->actingAs($manager)->get(route('dashboard.performance'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard.working-hours.index').'"', false);
    }

    public function test_manager_can_view_and_update_their_own_branch_hours(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);

        $this->actingAs($manager)->get(route('dashboard.working-hours.index'))
            ->assertOk()
            ->assertSee('Working Hours');

        $this->actingAs($manager)->put(route('dashboard.working-hours.update'), [
            'days' => [
                1 => ['opens_at' => '09:00', 'closes_at' => '21:00'],
            ],
        ])->assertRedirect(route('dashboard.working-hours.index'));

        $this->assertDatabaseHas('branch_working_hours', [
            'branch_id' => $this->osu->id,
            'day_of_week' => 1,
            'opens_at' => '09:00:00',
            'closes_at' => '21:00:00',
        ]);
    }

    public function test_manager_cannot_update_another_branchs_hours(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);

        $this->actingAs($manager)->put(route('dashboard.working-hours.update'), [
            'branch' => $this->eastLegon->id,
            'days' => [
                1 => ['opens_at' => '09:00', 'closes_at' => '21:00'],
            ],
        ]);

        // The 'branch' input is ignored for a non-owner — the write still
        // lands on the manager's own (BranchContext-resolved) branch.
        $this->assertDatabaseHas('branch_working_hours', [
            'branch_id' => $this->osu->id,
            'day_of_week' => 1,
        ]);
        $this->assertDatabaseMissing('branch_working_hours', [
            'branch_id' => $this->eastLegon->id,
        ]);
    }

    public function test_owner_can_view_and_update_any_branchs_hours_via_the_selector(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        $this->actingAs($owner)->put(route('dashboard.working-hours.update'), [
            'branch' => $this->eastLegon->id,
            'days' => [
                3 => ['opens_at' => '11:00', 'closes_at' => '23:00'],
            ],
        ])->assertRedirect(route('dashboard.working-hours.index', ['branch' => $this->eastLegon->id]));

        $this->assertDatabaseHas('branch_working_hours', [
            'branch_id' => $this->eastLegon->id,
            'day_of_week' => 3,
            'opens_at' => '11:00:00',
            'closes_at' => '23:00:00',
        ]);
    }

    public function test_a_day_left_blank_is_stored_as_closed(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        BranchWorkingHour::create([
            'branch_id' => $this->osu->id, 'day_of_week' => 7, 'opens_at' => '10:00', 'closes_at' => '18:00',
        ]);

        $this->actingAs($owner)->put(route('dashboard.working-hours.update'), [
            'branch' => $this->osu->id,
            'days' => [
                7 => ['opens_at' => null, 'closes_at' => null],
            ],
        ]);

        $this->assertDatabaseHas('branch_working_hours', [
            'branch_id' => $this->osu->id,
            'day_of_week' => 7,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        $this->actingAs($owner)->put(route('dashboard.working-hours.update'), [
            'branch' => $this->osu->id,
            'days' => [
                1 => ['opens_at' => '18:00', 'closes_at' => '09:00'],
            ],
        ])->assertSessionHasErrors('days.1.closes_at');
    }

    public function test_only_one_of_opens_or_closes_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        $this->actingAs($owner)->put(route('dashboard.working-hours.update'), [
            'branch' => $this->osu->id,
            'days' => [
                1 => ['opens_at' => '09:00', 'closes_at' => null],
            ],
        ])->assertSessionHasErrors('days.1.closes_at');
    }

    public function test_a_branch_with_no_working_hours_configured_is_always_open(): void
    {
        // Osu has zero branch_working_hours rows in this test's setUp —
        // the feature simply hasn't been touched for it yet, which must
        // not silently start blocking its (real, already-flowing) orders.
        $this->assertTrue(app(WorkingHoursService::class)->isOpenNow($this->osu));
    }

    public function test_branch_is_open_within_its_configured_window(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(15, 0));

        BranchWorkingHour::create(['branch_id' => $this->osu->id, 'day_of_week' => 1, 'opens_at' => '14:00', 'closes_at' => '23:00']);

        $this->assertTrue(app(WorkingHoursService::class)->isOpenNow($this->osu));
    }

    public function test_branch_is_closed_outside_its_configured_window(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(8, 0));

        BranchWorkingHour::create(['branch_id' => $this->osu->id, 'day_of_week' => 1, 'opens_at' => '14:00', 'closes_at' => '23:00']);

        $this->assertFalse(app(WorkingHoursService::class)->isOpenNow($this->osu));
    }

    public function test_branch_is_closed_on_a_day_left_blank(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::WEDNESDAY)->setTime(15, 0));

        // Only Monday configured — Wednesday has no row at all, same as a
        // day explicitly saved blank (schema.md: both null means closed).
        BranchWorkingHour::create(['branch_id' => $this->osu->id, 'day_of_week' => 1, 'opens_at' => '14:00', 'closes_at' => '23:00']);

        $this->assertFalse(app(WorkingHoursService::class)->isOpenNow($this->osu));
    }

    public function test_overnight_window_stays_open_past_midnight(): void
    {
        // Friday 18:00 - Saturday 02:00: still open at 01:00 on Saturday,
        // which is technically Friday's row (day_of_week 5), not Saturday's.
        BranchWorkingHour::create(['branch_id' => $this->osu->id, 'day_of_week' => 5, 'opens_at' => '18:00', 'closes_at' => '02:00']);

        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::SATURDAY)->setTime(1, 0));

        $this->assertTrue(app(WorkingHoursService::class)->isOpenNow($this->osu));
    }

    public function test_overnight_window_closes_after_its_early_morning_end(): void
    {
        BranchWorkingHour::create(['branch_id' => $this->osu->id, 'day_of_week' => 5, 'opens_at' => '18:00', 'closes_at' => '02:00']);

        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::SATURDAY)->setTime(3, 0));

        $this->assertFalse(app(WorkingHoursService::class)->isOpenNow($this->osu));
    }

    public function test_next_opening_finds_the_next_configured_day_and_time(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::WEDNESDAY)->setTime(9, 0));

        BranchWorkingHour::create(['branch_id' => $this->osu->id, 'day_of_week' => 5, 'opens_at' => '14:00', 'closes_at' => '23:00']);

        $nextOpening = app(WorkingHoursService::class)->nextOpening($this->osu);

        $this->assertNotNull($nextOpening);
        $this->assertSame(5, $nextOpening->isoWeekday());
        $this->assertSame('14:00:00', $nextOpening->format('H:i:s'));
    }
}
