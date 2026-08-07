<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RiderProfileTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function makeRider(): User
    {
        $rider = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $rider->assignRole('rider');

        return $rider;
    }

    public function test_rider_profile_page_loads(): void
    {
        $rider = $this->makeRider();

        $this->actingAs($rider)->get(route('rider.profile.edit'))->assertOk();
    }

    public function test_rider_can_update_name_phone_and_email(): void
    {
        $rider = $this->makeRider();

        $response = $this->actingAs($rider)->patch('/profile', [
            'name' => 'Kwame Updated',
            'email' => $rider->email,
            'phone' => '0241234567',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('rider.profile.edit'));

        $rider->refresh();
        $this->assertSame('Kwame Updated', $rider->name);
        $this->assertSame('+233241234567', $rider->phone);
    }

    public function test_rider_can_change_their_password(): void
    {
        $rider = $this->makeRider();

        $response = $this->actingAs($rider)->from(route('rider.profile.edit'))->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('rider.profile.edit'));
        $this->assertTrue(Hash::check('new-password-123', $rider->fresh()->password));
    }

    public function test_staff_profile_update_still_redirects_to_the_staff_profile_page(): void
    {
        $staff = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $staff->assignRole('staff');

        $response = $this->actingAs($staff)->patch('/profile', [
            'name' => 'Ama Staff',
            'email' => $staff->email,
        ]);

        $response->assertRedirect(route('profile.edit'));
    }
}
