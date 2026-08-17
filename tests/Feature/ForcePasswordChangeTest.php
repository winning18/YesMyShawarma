<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
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

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function flaggedUser(string $role = 'staff'): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('temp-one-time-password'),
            'must_change_password' => true,
        ]);
        $this->assignRoleAt($user, $role, $this->branch);

        return $user;
    }

    public function test_a_flagged_user_is_redirected_to_the_change_password_page(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('password.force-change'));
    }

    public function test_a_flagged_rider_is_redirected_too(): void
    {
        $rider = $this->flaggedUser('rider');

        $this->actingAs($rider)->get(route('rider.dashboard'))
            ->assertRedirect(route('password.force-change'));
    }

    public function test_the_force_change_page_itself_is_reachable_without_looping(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)->get(route('password.force-change'))->assertOk();
    }

    public function test_submitting_a_new_password_clears_the_flag_and_continues_to_the_dashboard(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'temp-one-time-password',
                'password' => 'a-real-password-123',
                'password_confirmation' => 'a-real-password-123',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('a-real-password-123', $user->password));

        // The flag is gone — a normal request now goes straight through.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_the_wrong_current_password_is_rejected(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)
            ->put(route('password.force-change.update'), [
                'current_password' => 'not-the-right-one',
                'password' => 'a-real-password-123',
                'password_confirmation' => 'a-real-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_an_unflagged_user_is_not_redirected(): void
    {
        $user = User::factory()->create();
        $this->assignRoleAt($user, 'staff', $this->branch);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_changing_password_from_the_ordinary_profile_form_also_clears_the_flag(): void
    {
        $user = $this->flaggedUser();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'temp-one-time-password',
                'password' => 'a-real-password-123',
                'password_confirmation' => 'a-real-password-123',
            ]);

        $this->assertFalse($user->fresh()->must_change_password);
    }
}
