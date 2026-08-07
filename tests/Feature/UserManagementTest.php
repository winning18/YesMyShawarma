<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    private function makeOwner(): User
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        return $owner;
    }

    public function test_owner_can_view_the_users_index(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('dashboard.users.index'))->assertOk();
    }

    public function test_manager_cannot_view_the_users_index(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.users.index'))->assertForbidden();
    }

    public function test_staff_cannot_view_the_users_index(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.users.index'))->assertForbidden();
    }

    public function test_owner_can_create_a_user_with_a_role_and_is_sent_a_reset_link(): void
    {
        Notification::fake();
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('dashboard.users.store'), [
            'name' => 'Kwame Rider',
            'email' => 'kwame@example.com',
            'phone' => '0241234567',
            'role' => 'rider',
            'branch_id' => $this->branch->id,
        ]);

        $newUser = User::where('email', 'kwame@example.com')->first();
        $this->assertNotNull($newUser);

        $response->assertRedirect(route('dashboard.users.edit', $newUser));
        $this->assertSame('+233241234567', $newUser->phone);
        $this->assertNotNull($newUser->email_verified_at);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->assertTrue($newUser->hasRole('rider'));

        Notification::assertSentTo($newUser, ResetPassword::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $owner = $this->makeOwner();
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($owner)->post(route('dashboard.users.store'), [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ])->assertSessionHasErrors('email');
    }

    public function test_owner_can_add_a_role_to_an_existing_user(): void
    {
        $owner = $this->makeOwner();
        $user = User::factory()->create();

        $secondBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->actingAs($owner)->post(route('dashboard.users.roles.add', $user), [
            'role' => 'staff',
            'branch_id' => $secondBranch->id,
        ])->assertRedirect();

        app(PermissionRegistrar::class)->setPermissionsTeamId($secondBranch->id);
        $this->assertTrue($user->fresh()->hasRole('staff'));
    }

    public function test_owner_can_remove_a_role_from_a_user(): void
    {
        $owner = $this->makeOwner();
        $user = User::factory()->create();
        $this->assignRoleAt($user, 'staff', $this->branch);

        $this->actingAs($owner)->delete(route('dashboard.users.roles.remove', $user), [
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ])->assertRedirect();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->assertFalse($user->fresh()->hasRole('staff'));
    }

    public function test_owner_cannot_remove_their_own_owner_role(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->delete(route('dashboard.users.roles.remove', $owner), [
            'role' => 'owner',
            'branch_id' => $this->branch->id,
        ]);

        $response->assertSessionHasErrors('role');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->assertTrue($owner->fresh()->hasRole('owner'));
    }

    public function test_owner_can_remove_another_owners_role(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();

        $this->actingAs($owner)->delete(route('dashboard.users.roles.remove', $otherOwner), [
            'role' => 'owner',
            'branch_id' => $this->branch->id,
        ])->assertRedirect();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->assertFalse($otherOwner->fresh()->hasRole('owner'));
    }
}
