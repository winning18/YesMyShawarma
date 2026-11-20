<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Services\Accounts\AccountSuspensionService;
use App\Services\Orders\RiderAssignmentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A rider counts as logged in from users.rider_last_seen_at
 * (MarkRiderSeen), never from sessions.user_id — which Laravel fills from
 * the request's default guard, so the rider's own /broadcasting/auth call
 * used to blank it a second after their dashboard loaded. See orders.md's
 * rider assignment section.
 */
class RiderPresenceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = $this->makeBranch('Osu');
    }

    private function makeBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name, 'slug' => Str::slug($name), 'phone' => '+233200'.random_int(100000, 999999), 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function userAt(string $role, Branch $branch, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function isAvailable(User $rider, ?Branch $branch = null): bool
    {
        return app(RiderAssignmentService::class)
            ->loggedInRiderIds(collect([$rider->id]), ($branch ?? $this->branch)->id)
            ->isNotEmpty();
    }

    private function riderIdsOnTheStaffDashboard(User $viewer): array
    {
        return collect($this->actingAs($viewer)->getJson(route('dashboard.riders'))->assertOk()->json('data'))
            ->pluck('id')->all();
    }

    public function test_a_rider_who_logs_in_shows_on_the_staff_dashboard(): void
    {
        $rider = $this->userAt('rider', $this->branch, ['email' => 'kwame@example.com', 'name' => 'Kwame']);
        $staff = $this->userAt('staff', $this->branch);

        $this->post(route('rider.login'), ['login' => 'kwame@example.com', 'password' => 'password'])
            ->assertRedirect(route('rider.dashboard'));
        $this->get(route('rider.dashboard'))->assertOk();

        $this->assertSame([$rider->id], $this->riderIdsOnTheStaffDashboard($staff));
    }

    public function test_a_rider_stays_available_after_their_dashboard_connects_to_live_updates(): void
    {
        config(['session.driver' => 'database']);
        $rider = $this->userAt('rider', $this->branch);

        $this->actingAs($rider, 'rider')->get(route('rider.dashboard'))->assertOk();
        // What Echo calls right after the dashboard loads — runs under the
        // default 'web' guard, which used to blank sessions.user_id.
        $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$rider->id]);

        $this->assertTrue($this->isAvailable($rider));
    }

    public function test_a_staff_login_in_the_same_browser_does_not_hide_the_rider(): void
    {
        $rider = $this->userAt('rider', $this->branch);
        $staff = $this->userAt('staff', $this->branch);

        $this->actingAs($rider, 'rider')->getJson(route('rider.orders.data'))->assertOk();

        // The staff dashboard's 15-second poll, from the same session.
        $this->assertSame([$rider->id], $this->riderIdsOnTheStaffDashboard($staff));
        $this->assertSame([$rider->id], $this->riderIdsOnTheStaffDashboard($staff));
    }

    public function test_a_rider_who_logs_out_is_no_longer_available(): void
    {
        $rider = $this->userAt('rider', $this->branch);

        $this->actingAs($rider, 'rider')->get(route('rider.dashboard'))->assertOk();
        $this->assertTrue($this->isAvailable($rider));

        $this->post(route('rider.logout'))->assertRedirect(route('rider.login'));

        $this->assertNull($rider->fresh()->rider_last_seen_at);
        $this->assertFalse($this->isAvailable($rider));
    }

    public function test_a_rider_not_seen_within_the_session_lifetime_is_no_longer_available(): void
    {
        $rider = $this->userAt('rider', $this->branch);
        $this->actingAs($rider, 'rider')->get(route('rider.dashboard'))->assertOk();

        $this->travel((int) config('session.lifetime') - 1)->minutes();
        $this->assertTrue($this->isAvailable($rider));

        $this->travel(2)->minutes();
        $this->assertFalse($this->isAvailable($rider));
    }

    public function test_the_dashboard_refresh_keeps_an_idle_rider_available(): void
    {
        $rider = $this->userAt('rider', $this->branch);
        $this->actingAs($rider, 'rider')->get(route('rider.dashboard'))->assertOk();

        $this->travel((int) config('session.lifetime') - 1)->minutes();
        $this->getJson(route('rider.orders.data'))->assertOk();
        $this->travel(2)->minutes();

        $this->assertTrue($this->isAvailable($rider));
    }

    public function test_seen_is_written_at_most_once_a_minute(): void
    {
        $rider = $this->userAt('rider', $this->branch);
        $this->freezeSecond();

        $this->actingAs($rider, 'rider')->getJson(route('rider.orders.data'))->assertOk();
        $first = $rider->fresh()->rider_last_seen_at;

        $this->travel(30)->seconds();
        $this->getJson(route('rider.orders.data'))->assertOk();
        $this->assertTrue($rider->fresh()->rider_last_seen_at->equalTo($first));

        $this->travel(31)->seconds();
        $this->getJson(route('rider.orders.data'))->assertOk();
        $this->assertTrue($rider->fresh()->rider_last_seen_at->gt($first));
    }

    public function test_a_rider_who_must_change_their_password_is_not_available_yet(): void
    {
        $rider = $this->userAt('rider', $this->branch);
        $rider->forceFill(['must_change_password' => true])->save();

        $this->actingAs($rider, 'rider')->get(route('rider.dashboard'))->assertRedirect(route('password.force-change'));

        $this->assertNull($rider->fresh()->rider_last_seen_at);
    }

    public function test_suspending_a_rider_takes_them_off_the_list_even_once_lifted(): void
    {
        $owner = $this->userAt('owner', $this->branch);
        $rider = $this->userAt('rider', $this->branch);
        $this->actingAs($rider, 'rider')->get(route('rider.dashboard'))->assertOk();

        $suspensions = app(AccountSuspensionService::class);
        $suspensions->suspend($rider, $owner, 'Late deliveries');
        $suspensions->lift($rider->fresh(), $owner);

        $this->assertFalse($this->isAvailable($rider));
    }

    public function test_staff_get_each_riders_branch(): void
    {
        $rider = $this->userAt('rider', $this->branch);
        $staff = $this->userAt('staff', $this->branch);
        $this->actingAs($rider, 'rider')->get(route('rider.dashboard'))->assertOk();

        $this->actingAs($staff)->getJson(route('dashboard.riders'))
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $rider->id, 'name' => $rider->name, 'branch_id' => $this->branch->id]]]);
    }

    public function test_an_owner_viewing_all_branches_gets_every_branchs_riders(): void
    {
        $owner = $this->userAt('owner', $this->branch);
        $other = $this->makeBranch('Pokuase');
        $osuRider = $this->userAt('rider', $this->branch);
        $pokuaseRider = $this->userAt('rider', $other);

        $this->actingAs($osuRider, 'rider')->get(route('rider.dashboard'))->assertOk();
        $this->actingAs($pokuaseRider, 'rider')->get(route('rider.dashboard'))->assertOk();

        // The owner's own browser: no branch picked, so no current branch.
        $this->flushSession();
        $riders = collect($this->actingAs($owner)->getJson(route('dashboard.riders'))->assertOk()->json('data'))
            ->pluck('branch_id', 'id');

        $this->assertEquals([$osuRider->id => $this->branch->id, $pokuaseRider->id => $other->id], $riders->sortKeys()->all());
    }

    public function test_the_migration_keeps_riders_who_were_available_on_the_day(): void
    {
        $rider = $this->userAt('rider', $this->branch);
        $staff = $this->userAt('staff', $this->branch);
        $idleRider = $this->userAt('rider', $this->branch);

        Schema::table('users', fn ($table) => $table->dropColumn('rider_last_seen_at'));
        $session = fn (User $user, int $at) => DB::table('sessions')->insert([
            'id' => Str::random(40), 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test',
            'payload' => base64_encode(serialize([])), 'last_activity' => $at,
        ]);
        $session($rider, now()->subMinutes(5)->getTimestamp());
        $session($staff, now()->getTimestamp());
        $session($idleRider, now()->subMinutes((int) config('session.lifetime') + 5)->getTimestamp());

        (require database_path('migrations/2026_11_20_120000_add_rider_last_seen_at_to_users.php'))->up();

        $this->assertNotNull($rider->fresh()->rider_last_seen_at);
        $this->assertNull($staff->fresh()->rider_last_seen_at);
        $this->assertNull($idleRider->fresh()->rider_last_seen_at);
    }
}
