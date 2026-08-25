<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_manager_can_view_the_users_index_but_not_create_users(): void
    {
        // A manager is here for exactly one thing — transferring a
        // staff/rider's branch (users.transfer_branch) — not the full
        // create/delete/role-management surface users.manage grants.
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.users.index'))
            ->assertOk()
            ->assertDontSee(route('dashboard.users.create'));

        $this->actingAs($manager)->post(route('dashboard.users.store'), [
            'name' => 'Someone New', 'email' => 'someone@example.com', 'role' => 'staff', 'branch_id' => $this->branch->id,
        ])->assertForbidden();
    }

    public function test_staff_cannot_view_the_users_index(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.users.index'))->assertForbidden();
    }

    public function test_owner_can_create_a_user_with_a_role_and_gets_a_one_time_password(): void
    {
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
        $this->assertTrue($newUser->must_change_password);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->assertTrue($newUser->hasRole('rider'));

        $temporaryPassword = $response->getSession()->get('temporary_password');
        $this->assertNotNull($temporaryPassword);
        $this->assertTrue(Hash::check($temporaryPassword, $newUser->password));
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

    public function test_owner_can_delete_a_user(): void
    {
        $owner = $this->makeOwner();
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($owner)->delete(route('dashboard.users.destroy', $staff))
            ->assertRedirect(route('dashboard.users.index'));

        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }

    public function test_deleting_a_user_scrubs_their_pii(): void
    {
        $owner = $this->makeOwner();
        $staff = User::factory()->create([
            'name' => 'Kojo Staff', 'email' => 'kojo@example.com', 'phone' => '+233241234567',
        ]);
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($owner)->delete(route('dashboard.users.destroy', $staff));

        $trashed = $staff->fresh();
        $this->assertNotSame('Kojo Staff', $trashed->name);
        $this->assertNotSame('kojo@example.com', $trashed->email);
        $this->assertNull($trashed->phone);
    }

    public function test_a_deleted_users_email_and_phone_can_be_reused_on_a_new_account(): void
    {
        $owner = $this->makeOwner();
        $staff = User::factory()->create(['email' => 'kojo@example.com', 'phone' => '+233241234567']);
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($owner)->delete(route('dashboard.users.destroy', $staff));

        $this->actingAs($owner)->post(route('dashboard.users.store'), [
            'name' => 'New Kojo',
            'email' => 'kojo@example.com',
            'phone' => '0241234567',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'kojo@example.com', 'name' => 'New Kojo']);
    }

    public function test_deleted_user_no_longer_appears_in_the_index(): void
    {
        $owner = $this->makeOwner();
        $staff = User::factory()->create(['name' => 'Kojo Staff']);
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($owner)->delete(route('dashboard.users.destroy', $staff));

        // The delete redirect's own flash message names the deleted user
        // ("Kojo Staff has been deleted.") — that flash only survives one
        // request, so a second, unrelated request is the one that actually
        // proves the list itself no longer renders them.
        $this->actingAs($owner)->get(route('dashboard.users.index'));

        $this->actingAs($owner)->get(route('dashboard.users.index'))
            ->assertDontSee('Kojo Staff');
    }

    public function test_owner_cannot_delete_their_own_account(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->delete(route('dashboard.users.destroy', $owner));

        $response->assertSessionHasErrors('user');
        $this->assertNull($owner->fresh()->deleted_at);
    }

    public function test_manager_cannot_delete_a_user(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($manager)->delete(route('dashboard.users.destroy', $staff))
            ->assertForbidden();

        $this->assertNull($staff->fresh()->deleted_at);
    }
}
