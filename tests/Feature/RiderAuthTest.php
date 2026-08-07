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
        $this->assertAuthenticatedAs($rider);
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
        $this->assertGuest();
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
        $this->assertAuthenticatedAs($rider);
    }

    public function test_rider_can_log_out(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $branch);

        $response = $this->actingAs($rider)->post(route('rider.logout'));

        $response->assertRedirect(route('rider.login'));
        $this->assertGuest();
    }
}
