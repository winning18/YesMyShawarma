<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    public function test_a_rider_only_account_cannot_log_in_via_the_staff_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $branch);

        $response = $this->post('/login', [
            'email' => $rider->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_hybrid_staff_and_rider_account_can_still_log_in_via_the_staff_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $branchA = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $branchB = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $hybrid = User::factory()->create();
        $this->assignRoleAt($hybrid, 'rider', $branchA);
        $this->assignRoleAt($hybrid, 'staff', $branchB);

        $response = $this->post('/login', [
            'email' => $hybrid->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($hybrid);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_an_owner_account_can_log_in_via_the_staff_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $branch);

        $response = $this->post('/login', [
            'email' => $owner->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($owner);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
