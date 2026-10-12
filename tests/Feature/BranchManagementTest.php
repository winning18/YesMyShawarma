<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchManagementTest extends TestCase
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

    public function test_owner_can_view_the_branches_index(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('dashboard.branches.index'))->assertOk();
    }

    public function test_staff_cannot_view_the_branches_index(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.branches.index'))->assertForbidden();
    }

    public function test_owner_can_create_a_branch_with_an_auto_generated_slug(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('dashboard.branches.store'), [
            'name' => 'Spintex',
            'phone' => '+233200000009',
            'address' => 'Spintex Road, Accra',
            'lat' => 5.62,
            'lng' => -0.13,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
            'is_accepting_orders' => '1',
            'is_active' => '1',
        ]);

        $branch = Branch::where('name', 'Spintex')->first();

        $response->assertRedirect(route('dashboard.branches.edit', $branch));
        $this->assertSame('spintex', $branch->slug);
        $this->assertTrue($branch->is_accepting_orders);
        $this->assertTrue($branch->is_active);
    }

    /**
     * Regression: creating a branch never attached existing menu items to
     * it at all, unlike creating a menu item (which attaches to every
     * existing branch — MenuItemManagementController::store()). The new
     * branch's items then showed as "Available" in the dashboard anyway
     * (a missing pivot row defaults to that in the UI — see
     * MenuItemManagementTest's own regression test), while the
     * customer-facing menu, which requires a real pivot row, showed
     * nothing at all.
     */
    public function test_owner_creating_a_branch_attaches_every_existing_menu_item(): void
    {
        $owner = $this->makeOwner();
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);

        $this->actingAs($owner)->post(route('dashboard.branches.store'), [
            'name' => 'GIMPA Branch',
            'phone' => '+233200000010',
            'address' => 'GIMPA, Accra',
            'lat' => 5.65,
            'lng' => -0.17,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
            'is_accepting_orders' => '1',
            'is_active' => '1',
        ]);

        $branch = Branch::where('name', 'GIMPA Branch')->first();

        $this->assertDatabaseHas('branch_menu_item', [
            'branch_id' => $branch->id, 'menu_item_id' => $item->id, 'is_available' => true,
        ]);
    }

    public function test_a_colliding_name_gets_a_numbered_slug(): void
    {
        $owner = $this->makeOwner();
        Branch::create([
            'name' => 'Spintex', 'slug' => 'spintex', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->actingAs($owner)->post(route('dashboard.branches.store'), [
            'name' => 'Spintex',
            'phone' => '+233200000009',
            'address' => 'Spintex Road, Accra',
            'lat' => 5.62,
            'lng' => -0.13,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
        ]);

        $this->assertDatabaseHas('branches', ['name' => 'Spintex', 'slug' => 'spintex-2']);
    }

    public function test_creating_a_branch_without_checking_the_boxes_defaults_to_false(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('dashboard.branches.store'), [
            'name' => 'Spintex',
            'phone' => '+233200000009',
            'address' => 'Spintex Road, Accra',
            'lat' => 5.62,
            'lng' => -0.13,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
        ]);

        $branch = Branch::where('name', 'Spintex')->first();
        $this->assertFalse($branch->is_accepting_orders);
        $this->assertFalse($branch->is_active);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->post(route('dashboard.branches.store'), [
            'name' => 'Spintex',
            'phone' => '+233200000009',
            'address' => 'Spintex Road, Accra',
            'lat' => 200,
            'lng' => -0.13,
            'opens_at' => '09:00',
            'closes_at' => '21:00',
        ])->assertSessionHasErrors('lat');
    }

    public function test_owner_can_update_a_branch(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->put(route('dashboard.branches.update', $this->branch), [
            'name' => 'Osu Updated',
            'slug' => 'osu',
            'phone' => '+233200000001',
            'address' => 'New address',
            'lat' => 5.5,
            'lng' => -0.1,
            'opens_at' => '11:00',
            'closes_at' => '23:00',
            'is_accepting_orders' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('dashboard.branches.edit', $this->branch));
        $this->assertSame('Osu Updated', $this->branch->fresh()->name);
        $this->assertSame('New address', $this->branch->fresh()->address);
    }

    /**
     * Regression: re-saving an existing branch's own edit form (no
     * changes needed) now self-heals any menu item missing a pivot row
     * for it — the same fix as store()'s attach-on-create, for a branch
     * that predates it. Must never reset an item staff deliberately
     * toggled off at this branch back to available just by saving the
     * form again.
     */
    public function test_updating_a_branch_attaches_any_missing_menu_item_without_resetting_existing_ones(): void
    {
        $owner = $this->makeOwner();
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $missingItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $soldOutItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Beef Burger', 'slug' => 'beef-burger', 'base_price' => 4000,
        ]);
        $soldOutItem->branches()->attach($this->branch->id, ['is_available' => false]);

        $this->actingAs($owner)->put(route('dashboard.branches.update', $this->branch), [
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
            'is_accepting_orders' => '1', 'is_active' => '1',
        ]);

        $this->assertDatabaseHas('branch_menu_item', [
            'branch_id' => $this->branch->id, 'menu_item_id' => $missingItem->id, 'is_available' => true,
        ]);
        // The deliberately sold-out item must stay sold out, not get
        // reset to available just because the branch form was saved.
        $this->assertDatabaseHas('branch_menu_item', [
            'branch_id' => $this->branch->id, 'menu_item_id' => $soldOutItem->id, 'is_available' => false,
        ]);
    }

    public function test_owner_can_toggle_accepting_orders(): void
    {
        $owner = $this->makeOwner();
        $this->assertTrue($this->branch->fresh()->is_accepting_orders);

        $this->actingAs($owner)->post(route('dashboard.branches.toggle-accepting-orders', $this->branch))
            ->assertRedirect();

        $this->assertFalse($this->branch->fresh()->is_accepting_orders);

        $this->actingAs($owner)->post(route('dashboard.branches.toggle-accepting-orders', $this->branch));

        $this->assertTrue($this->branch->fresh()->is_accepting_orders);
    }

    public function test_owner_can_upload_and_remove_a_branch_image(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->post(route('dashboard.branches.image.update', $this->branch), [
                'image' => UploadedFile::fake()->image('branch.jpg'),
            ])
            ->assertRedirect();

        $this->branch->refresh();
        $this->assertNotNull($this->branch->image_path);
        Storage::disk('public')->assertExists($this->branch->image_path);

        $storedPath = $this->branch->image_path;

        $this->actingAs($owner)
            ->delete(route('dashboard.branches.image.destroy', $this->branch))
            ->assertRedirect();

        $this->branch->refresh();
        $this->assertNull($this->branch->image_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_manager_cannot_upload_a_branch_image(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)
            ->post(route('dashboard.branches.image.update', $this->branch), [
                'image' => UploadedFile::fake()->image('branch.jpg'),
            ])
            ->assertForbidden();
    }
}
