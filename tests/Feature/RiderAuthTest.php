<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RiderAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    public function test_rider_login_page_loads(): void
    {
        $this->get(route('rider.login'))->assertOk();
    }

    public function test_rider_can_log_in_and_is_sent_to_the_rider_dashboard(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $rider = User::factory()->create(['email' => 'kwame@example.com']);
        $this->assignRoleAt($rider, 'rider', $branch);

        $response = $this->post(route('rider.login'), [
            'login' => 'kwame@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('rider.dashboard'));
        $this->assertAuthenticatedAs($rider, 'rider');
    }

    public function test_an_account_without_the_rider_role_cannot_log_in_via_rider_login(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $staff = User::factory()->create(['email' => 'ama@example.com']);
        $this->assignRoleAt($staff, 'staff', $branch);

        $response = $this->post(route('rider.login'), [
            'login' => 'ama@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest('rider');
    }

    public function test_rider_can_log_in_with_a_phone_number(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $rider = User::factory()->create(['phone' => '+233241234567']);
        $this->assignRoleAt($rider, 'rider', $branch);

        $response = $this->post(route('rider.login'), [
            'login' => '0241234567',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('rider.dashboard'));
        $this->assertAuthenticatedAs($rider, 'rider');
    }

    public function test_rider_can_log_out(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $branch);

        $response = $this->actingAs($rider, 'rider')->post(route('rider.logout'));

        $response->assertRedirect(route('rider.login'));
        $this->assertGuest('rider');
    }

    /**
     * Regression test for the underlying problem this guard split fixes —
     * staff and rider logging in used to share the same 'web' guard, so
     * one login silently overwrote the other's session the moment either
     * happened in the same browser.
     */
    public function test_a_staff_login_and_a_rider_login_coexist_in_the_same_session(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $staff = User::factory()->create(['email' => 'staff@example.com']);
        $this->assignRoleAt($staff, 'staff', $branch);
        $rider = User::factory()->create(['email' => 'rider@example.com']);
        $this->assignRoleAt($rider, 'rider', $branch);

        $this->post(route('login'), ['email' => 'staff@example.com', 'password' => 'password']);
        $this->assertAuthenticatedAs($staff, 'web');

        $this->post(route('rider.login'), ['login' => 'rider@example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($rider, 'rider');
        $this->assertAuthenticatedAs($staff, 'web');
    }

    /**
     * Regression test for the other half of the same fix — logging out of
     * one guard must not flush the whole session (session()->invalidate()
     * does exactly that) and take a coexisting login on the other guard
     * down with it.
     */
    public function test_logging_out_of_one_guard_does_not_log_out_the_other(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $staff = User::factory()->create(['email' => 'staff@example.com']);
        $this->assignRoleAt($staff, 'staff', $branch);
        $rider = User::factory()->create(['email' => 'rider@example.com']);
        $this->assignRoleAt($rider, 'rider', $branch);

        $this->post(route('login'), ['email' => 'staff@example.com', 'password' => 'password']);
        $this->post(route('rider.login'), ['login' => 'rider@example.com', 'password' => 'password']);

        $this->post(route('rider.logout'));

        $this->assertGuest('rider');
        $this->assertAuthenticatedAs($staff, 'web');
    }
}
