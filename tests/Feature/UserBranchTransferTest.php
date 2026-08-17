<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserBranchTransferTest extends TestCase
{
    use RefreshDatabase;

    private Branch $odumase;

    private Branch $pokuase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->odumase = Branch::create([
            'name' => 'Odumase', 'slug' => 'odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->pokuase = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.7, 'lng' => -0.3, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function hasRoleAt(User $user, string $role, Branch $branch): bool
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);

        return $user->fresh()->hasRole($role);
    }

    private function makeOwner(): User
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->odumase);

        return $owner;
    }

    private function transferPayload(string $role, Branch $from, Branch $to): array
    {
        return ['role' => $role, 'from_branch_id' => $from->id, 'to_branch_id' => $to->id];
    }

    public function test_owner_can_transfer_a_rider_kofi_moves_from_odumase_to_pokuase(): void
    {
        $owner = $this->makeOwner();
        $kofi = User::factory()->create();
        $this->assignRoleAt($kofi, 'rider', $this->odumase);

        $this->actingAs($owner)
            ->post(route('dashboard.users.change_branch', $kofi), $this->transferPayload('rider', $this->odumase, $this->pokuase))
            ->assertRedirect();

        $this->assertFalse($this->hasRoleAt($kofi, 'rider', $this->odumase));
        $this->assertTrue($this->hasRoleAt($kofi, 'rider', $this->pokuase));
    }

    public function test_owner_can_transfer_staff_and_manager(): void
    {
        $owner = $this->makeOwner();

        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->odumase);
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->odumase);

        $this->actingAs($owner)
            ->post(route('dashboard.users.change_branch', $staff), $this->transferPayload('staff', $this->odumase, $this->pokuase))
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('dashboard.users.change_branch', $manager), $this->transferPayload('manager', $this->odumase, $this->pokuase))
            ->assertRedirect();

        $this->assertTrue($this->hasRoleAt($staff, 'staff', $this->pokuase));
        $this->assertTrue($this->hasRoleAt($manager, 'manager', $this->pokuase));
    }

    public function test_owner_cannot_transfer_an_owner_role_assignment(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();

        $this->actingAs($owner)
            ->post(route('dashboard.users.change_branch', $otherOwner), $this->transferPayload('owner', $this->odumase, $this->pokuase))
            ->assertForbidden();

        $this->assertTrue($this->hasRoleAt($otherOwner, 'owner', $this->odumase));
    }

    public function test_manager_can_transfer_a_staff_member_at_their_own_branch(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->odumase);

        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->odumase);

        $this->actingAs($manager)
            ->post(route('dashboard.users.change_branch', $staff), $this->transferPayload('staff', $this->odumase, $this->pokuase))
            ->assertRedirect();

        $this->assertTrue($this->hasRoleAt($staff, 'staff', $this->pokuase));
    }

    public function test_manager_can_transfer_a_rider_at_their_own_branch(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->odumase);

        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->odumase);

        $this->actingAs($manager)
            ->post(route('dashboard.users.change_branch', $rider), $this->transferPayload('rider', $this->odumase, $this->pokuase))
            ->assertRedirect();

        $this->assertTrue($this->hasRoleAt($rider, 'rider', $this->pokuase));
    }

    public function test_manager_cannot_transfer_a_staff_member_at_a_branch_they_dont_manage(): void
    {
        // Manager only manages Odumase — this staff member's assignment is
        // at Pokuase, outside their authority (permissions.md: same
        // branch-scoping rule as OrderPolicy::checkAtOrderBranch).
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->odumase);

        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->pokuase);

        $this->actingAs($manager)
            ->post(route('dashboard.users.change_branch', $staff), $this->transferPayload('staff', $this->pokuase, $this->odumase))
            ->assertForbidden();

        $this->assertTrue($this->hasRoleAt($staff, 'staff', $this->pokuase));
    }

    public function test_manager_cannot_transfer_another_manager(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->odumase);

        $otherManager = User::factory()->create();
        $this->assignRoleAt($otherManager, 'manager', $this->odumase);

        $this->actingAs($manager)
            ->post(route('dashboard.users.change_branch', $otherManager), $this->transferPayload('manager', $this->odumase, $this->pokuase))
            ->assertForbidden();

        $this->assertTrue($this->hasRoleAt($otherManager, 'manager', $this->odumase));
    }

    public function test_manager_cannot_transfer_themselves(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->odumase);

        $this->actingAs($manager)
            ->post(route('dashboard.users.change_branch', $manager), $this->transferPayload('staff', $this->odumase, $this->pokuase))
            ->assertForbidden();
    }

    public function test_staff_cannot_transfer_anyone(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->odumase);

        $otherStaff = User::factory()->create();
        $this->assignRoleAt($otherStaff, 'staff', $this->odumase);

        $this->actingAs($staff)
            ->post(route('dashboard.users.change_branch', $otherStaff), $this->transferPayload('staff', $this->odumase, $this->pokuase))
            ->assertForbidden();
    }

    public function test_rider_can_already_hold_the_same_role_at_a_second_branch_without_transferring(): void
    {
        // The "add role" flow (unchanged) already supports a rider holding
        // roles at multiple branches at once — a transfer replaces one
        // assignment, it isn't the only way to reach a second branch.
        $owner = $this->makeOwner();
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->odumase);

        $this->actingAs($owner)->post(route('dashboard.users.roles.add', $rider), [
            'role' => 'rider', 'branch_id' => $this->pokuase->id,
        ])->assertRedirect();

        $this->assertTrue($this->hasRoleAt($rider, 'rider', $this->odumase));
        $this->assertTrue($this->hasRoleAt($rider, 'rider', $this->pokuase));
    }
}
