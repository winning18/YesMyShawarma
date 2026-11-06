<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private Branch $osu;

    private Branch $labone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->osu = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->labone = Branch::create([
            'name' => 'Labone', 'slug' => 'labone', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function userAt(string $role, Branch ...$branches): User
    {
        $user = User::factory()->create();

        foreach ($branches as $branch) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
            $user->assignRole($role);
        }

        return $user;
    }

    private function openShiftAt(Branch $branch): void
    {
        Shift::create(['user_id' => $this->userAt('staff', $branch)->id, 'branch_id' => $branch->id, 'started_at' => now()->subHour()]);
    }

    public function test_the_owner_can_switch_branch_while_the_current_one_has_an_open_shift(): void
    {
        $owner = $this->userAt('owner', $this->osu);
        $this->openShiftAt($this->osu);

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->osu->id])
            ->post(route('branches.select.store'), ['branch_id' => $this->labone->id, 'return_to' => '/dashboard/menu-items'])
            ->assertRedirect('/dashboard/menu-items');

        $this->assertSame($this->labone->id, session('current_branch_id'));
    }

    public function test_a_manager_can_switch_too(): void
    {
        $manager = $this->userAt('manager', $this->osu, $this->labone);
        $this->openShiftAt($this->osu);

        $this->actingAs($manager)->withSession(['current_branch_id' => $this->osu->id])
            ->post(route('branches.select.store'), ['branch_id' => $this->labone->id]);

        $this->assertSame($this->labone->id, session('current_branch_id'));
    }

    public function test_staff_stay_locked_while_their_branch_has_an_open_shift(): void
    {
        $staff = $this->userAt('staff', $this->osu, $this->labone);
        $this->openShiftAt($this->osu);

        $this->actingAs($staff)->withSession(['current_branch_id' => $this->osu->id])
            ->post(route('branches.select.store'), ['branch_id' => $this->labone->id])
            ->assertSessionHas('status', 'You have an active shift running — end it before switching branches.');

        $this->assertSame($this->osu->id, session('current_branch_id'));
    }

    public function test_return_to_only_accepts_paths_on_this_site(): void
    {
        $owner = $this->userAt('owner', $this->osu);

        foreach (['https://evil.example', '//evil.example', '/\\evil.example'] as $outside) {
            $this->actingAs($owner)->withSession(['current_branch_id' => $this->osu->id])
                ->post(route('branches.select.store'), ['branch_id' => $this->labone->id, 'return_to' => $outside])
                ->assertSessionHasErrors('return_to');
        }
    }

    public function test_menu_pages_and_sidebar_offer_the_switcher_to_multi_branch_overseers_only(): void
    {
        $owner = $this->userAt('owner', $this->osu);

        foreach (['dashboard.menu-items.index', 'dashboard.menu-items.availability', 'dashboard.menu-items.timetable'] as $route) {
            $this->actingAs($owner)->withSession(['current_branch_id' => $this->osu->id])
                ->get(route($route))->assertOk()
                ->assertSee('id="branch-switcher"', false)
                ->assertSee('Switch branch');
        }

        $singleBranchManager = $this->userAt('manager', $this->osu);
        $this->actingAs($singleBranchManager)->withSession(['current_branch_id' => $this->osu->id])
            ->get(route('dashboard.menu-items.index'))->assertOk()
            ->assertDontSee('id="branch-switcher"', false)
            ->assertDontSee('Switch branch');
    }
}
