<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemSchedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MenuItemScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->item->branches()->attach([$this->branch->id, $this->otherBranch->id], ['is_available' => true]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    public function test_manager_can_view_the_timetable(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.menu-items.timetable'))
            ->assertOk()
            ->assertSee('Chicken Shawarma');
    }

    public function test_staff_cannot_view_the_timetable(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.menu-items.timetable'))->assertForbidden();
    }

    public function test_owner_with_no_branch_selected_is_sent_to_pick_one_and_returns_to_timetable(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $this->actingAs($owner)->get(route('dashboard.menu-items.timetable'))
            ->assertRedirect(route('branches.select'));

        $this->actingAs($owner)
            ->post(route('branches.select.store'), ['branch_id' => $this->branch->id])
            ->assertRedirect(route('dashboard.menu-items.timetable'));
    }

    public function test_manager_can_set_a_schedule(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.menu-items.schedule.update', $this->item), [
            'days' => [5, 6, 0],
            'starts_at' => '18:00',
            'ends_at' => '22:00',
        ])->assertRedirect();

        $schedules = $this->item->schedules()->where('branch_id', $this->branch->id)->orderBy('day_of_week')->get();

        $this->assertCount(3, $schedules);
        $this->assertSame([0, 5, 6], $schedules->pluck('day_of_week')->all());
        $this->assertSame('18:00', $schedules->first()->starts_at);
    }

    public function test_setting_a_schedule_replaces_the_previous_one(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.menu-items.schedule.update', $this->item), [
            'days' => [1, 2],
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);

        $this->actingAs($manager)->post(route('dashboard.menu-items.schedule.update', $this->item), [
            'days' => [5],
            'starts_at' => '18:00',
            'ends_at' => '22:00',
        ]);

        $schedules = $this->item->schedules()->where('branch_id', $this->branch->id)->get();
        $this->assertCount(1, $schedules);
        $this->assertSame(5, $schedules->first()->day_of_week);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.menu-items.schedule.update', $this->item), [
            'days' => [5],
            'starts_at' => '22:00',
            'ends_at' => '18:00',
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_schedule_is_scoped_to_the_current_branch_only(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.menu-items.schedule.update', $this->item), [
            'days' => [5],
            'starts_at' => '18:00',
            'ends_at' => '22:00',
        ]);

        $this->assertSame(0, $this->item->schedules()->where('branch_id', $this->otherBranch->id)->count());
    }

    public function test_manager_can_clear_a_schedule(): void
    {
        $manager = $this->makeManager();
        MenuItemSchedule::create([
            'menu_item_id' => $this->item->id, 'branch_id' => $this->branch->id,
            'day_of_week' => 5, 'starts_at' => '18:00', 'ends_at' => '22:00',
        ]);

        $this->actingAs($manager)->delete(route('dashboard.menu-items.schedule.destroy', $this->item))
            ->assertRedirect();

        $this->assertSame(0, $this->item->schedules()->where('branch_id', $this->branch->id)->count());
    }
}
