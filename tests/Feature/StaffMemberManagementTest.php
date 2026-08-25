<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StaffMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

    public function test_owner_can_view_the_staff_members_index(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('dashboard.staff-members.index'))->assertOk();
    }

    public function test_manager_can_view_the_staff_members_index(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.staff-members.index'))->assertOk();
    }

    public function test_staff_cannot_view_the_staff_members_index(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.staff-members.index'))->assertForbidden();
    }

    public function test_owner_can_create_a_staff_member(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('dashboard.staff-members.store'), [
            'name' => 'Ama Owusu',
            'title' => 'Branch Manager',
            'sort_order' => 3,
            'is_active' => '1',
        ]);

        $staffMember = StaffMember::where('name', 'Ama Owusu')->first();

        $response->assertRedirect(route('dashboard.staff-members.edit', $staffMember));
        $this->assertSame('Branch Manager', $staffMember->title);
        $this->assertSame(3, $staffMember->sort_order);
        $this->assertTrue($staffMember->is_active);
    }

    public function test_owner_can_update_a_staff_member(): void
    {
        $owner = $this->makeOwner();
        $staffMember = StaffMember::create(['name' => 'Ama Owusu']);

        $this->actingAs($owner)->put(route('dashboard.staff-members.update', $staffMember), [
            'name' => 'Ama K. Owusu',
            'title' => 'Store Lead',
            'is_active' => '0',
        ]);

        $staffMember->refresh();
        $this->assertSame('Ama K. Owusu', $staffMember->name);
        $this->assertSame('Store Lead', $staffMember->title);
        $this->assertFalse($staffMember->is_active);
    }

    public function test_owner_can_upload_and_remove_a_staff_members_photo(): void
    {
        $owner = $this->makeOwner();
        $staffMember = StaffMember::create(['name' => 'Ama Owusu']);

        $this->actingAs($owner)
            ->post(route('dashboard.staff-members.image.update', $staffMember), [
                'image' => UploadedFile::fake()->image('ama.jpg'),
            ])
            ->assertRedirect();

        $staffMember->refresh();
        $this->assertNotNull($staffMember->photo_path);
        Storage::disk('public')->assertExists($staffMember->photo_path);

        $storedPath = $staffMember->photo_path;

        $this->actingAs($owner)
            ->delete(route('dashboard.staff-members.image.destroy', $staffMember))
            ->assertRedirect();

        $staffMember->refresh();
        $this->assertNull($staffMember->photo_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_manager_can_create_a_staff_member(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)
            ->post(route('dashboard.staff-members.store'), ['name' => 'Ama Owusu'])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_members', ['name' => 'Ama Owusu']);
    }

    public function test_staff_cannot_create_a_staff_member(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)
            ->post(route('dashboard.staff-members.store'), ['name' => 'Ama Owusu'])
            ->assertForbidden();

        $this->assertDatabaseMissing('staff_members', ['name' => 'Ama Owusu']);
    }

    public function test_owner_can_delete_a_staff_member(): void
    {
        $owner = $this->makeOwner();
        $staffMember = StaffMember::create(['name' => 'Ama Owusu']);

        $this->actingAs($owner)
            ->delete(route('dashboard.staff-members.destroy', $staffMember))
            ->assertRedirect(route('dashboard.staff-members.index'));

        $this->assertDatabaseMissing('staff_members', ['id' => $staffMember->id]);
    }

    public function test_deleting_a_staff_member_also_removes_their_photo(): void
    {
        $owner = $this->makeOwner();
        $staffMember = StaffMember::create(['name' => 'Ama Owusu']);

        $this->actingAs($owner)->post(route('dashboard.staff-members.image.update', $staffMember), [
            'image' => UploadedFile::fake()->image('ama.jpg'),
        ]);

        $storedPath = $staffMember->fresh()->photo_path;
        Storage::disk('public')->assertExists($storedPath);

        $this->actingAs($owner)->delete(route('dashboard.staff-members.destroy', $staffMember));

        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_manager_can_delete_a_staff_member(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);
        $staffMember = StaffMember::create(['name' => 'Ama Owusu']);

        $this->actingAs($manager)
            ->delete(route('dashboard.staff-members.destroy', $staffMember))
            ->assertRedirect(route('dashboard.staff-members.index'));

        $this->assertDatabaseMissing('staff_members', ['id' => $staffMember->id]);
    }

    public function test_staff_cannot_delete_a_staff_member(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $staffMember = StaffMember::create(['name' => 'Ama Owusu']);

        $this->actingAs($staff)
            ->delete(route('dashboard.staff-members.destroy', $staffMember))
            ->assertForbidden();

        $this->assertDatabaseHas('staff_members', ['id' => $staffMember->id]);
    }
}
