<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\OptionGroup;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MenuItemManagementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        return $staff;
    }

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    private function makeItem(): MenuItem
    {
        $item = MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $item->branches()->attach([$this->branch->id, $this->otherBranch->id], ['is_available' => true]);

        return $item;
    }

    public function test_staff_can_view_the_menu(): void
    {
        $staff = $this->makeStaff();
        $this->makeItem();

        $this->actingAs($staff)->get(route('dashboard.menu-items.index'))
            ->assertOk()
            ->assertSee('Chicken Shawarma');
    }

    public function test_owner_with_no_branch_selected_is_sent_to_pick_one_and_returns_here(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $this->actingAs($owner)->get(route('dashboard.menu-items.index'))
            ->assertRedirect(route('branches.select'));

        $this->actingAs($owner)
            ->post(route('branches.select.store'), ['branch_id' => $this->branch->id])
            ->assertRedirect(route('dashboard.menu-items.index'));
    }

    public function test_staff_can_toggle_availability_at_their_branch_only(): void
    {
        $staff = $this->makeStaff();
        $item = $this->makeItem();

        $this->actingAs($staff)->post(route('dashboard.menu-items.toggle-availability', $item))
            ->assertRedirect();

        $this->assertFalse((bool) $item->branches()->where('branches.id', $this->branch->id)->first()->pivot->is_available);
        $this->assertTrue((bool) $item->branches()->where('branches.id', $this->otherBranch->id)->first()->pivot->is_available);
    }

    public function test_staff_can_view_the_availability_page(): void
    {
        $staff = $this->makeStaff();
        $this->makeItem();

        $this->actingAs($staff)->get(route('dashboard.menu-items.availability'))
            ->assertOk()
            ->assertSee('Chicken Shawarma');
    }

    public function test_owner_with_no_branch_selected_is_sent_to_pick_one_and_returns_to_availability(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $this->actingAs($owner)->get(route('dashboard.menu-items.availability'))
            ->assertRedirect(route('branches.select'));

        $this->actingAs($owner)
            ->post(route('branches.select.store'), ['branch_id' => $this->branch->id])
            ->assertRedirect(route('dashboard.menu-items.availability'));
    }

    public function test_availability_page_toggle_works_the_same_as_the_full_menu_page(): void
    {
        $staff = $this->makeStaff();
        $item = $this->makeItem();

        $this->actingAs($staff)->post(route('dashboard.menu-items.toggle-availability', $item))
            ->assertRedirect();

        $this->assertFalse((bool) $item->branches()->where('branches.id', $this->branch->id)->first()->pivot->is_available);
    }

    public function test_staff_cannot_edit_menu_content(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->get(route('dashboard.menu-items.create'))->assertForbidden();
    }

    public function test_manager_can_create_a_menu_item_attached_to_every_branch(): void
    {
        $manager = $this->makeManager();

        $response = $this->actingAs($manager)->post(route('dashboard.menu-items.store'), [
            'category_id' => $this->category->id,
            'name' => 'Beef Shawarma',
            'description' => 'Grilled beef, fresh veg.',
            'base_price' => '65.00',
            'sort_order' => 1,
            'is_active' => '1',
        ]);

        $item = MenuItem::where('name', 'Beef Shawarma')->first();

        $response->assertRedirect(route('dashboard.menu-items.edit', $item));
        $this->assertSame(6500, $item->base_price);
        $this->assertSame(2, $item->branches()->count());
        $this->assertTrue((bool) $item->branches()->where('branches.id', $this->branch->id)->first()->pivot->is_available);
    }

    public function test_manager_can_create_a_menu_item_with_a_photo_in_one_step(): void
    {
        $manager = $this->makeManager();

        $response = $this->actingAs($manager)->post(route('dashboard.menu-items.store'), [
            'category_id' => $this->category->id,
            'name' => 'Lamb Shawarma',
            'base_price' => '70.00',
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('item.jpg'),
        ]);

        $item = MenuItem::where('name', 'Lamb Shawarma')->first();

        $response->assertRedirect(route('dashboard.menu-items.edit', $item));
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_manager_can_update_a_menu_item_and_its_option_groups(): void
    {
        $manager = $this->makeManager();
        $item = $this->makeItem();
        $group = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);

        $response = $this->actingAs($manager)->put(route('dashboard.menu-items.update', $item), [
            'category_id' => $this->category->id,
            'name' => 'Chicken Shawarma Deluxe',
            'slug' => 'chicken-shawarma',
            'base_price' => '55.00',
            'sort_order' => 0,
            'is_active' => '1',
            'option_group_ids' => [$group->id],
        ]);

        $response->assertRedirect(route('dashboard.menu-items.edit', $item));
        $item->refresh();
        $this->assertSame('Chicken Shawarma Deluxe', $item->name);
        $this->assertSame(5500, $item->base_price);
        $this->assertTrue($item->optionGroups->contains($group));
    }

    public function test_edit_page_shows_previous_and_next_navigation_links(): void
    {
        $manager = $this->makeManager();
        $first = $this->makeItem();
        $middle = MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Falafel Wrap', 'slug' => 'falafel-wrap', 'base_price' => 4000, 'sort_order' => 1,
        ]);
        MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Kofta Wrap', 'slug' => 'kofta-wrap', 'base_price' => 4500, 'sort_order' => 2,
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard.menu-items.edit', $middle));

        $response->assertOk();
        $response->assertSee(route('dashboard.menu-items.edit', $first), false);
        $response->assertSee($first->name);
    }

    public function test_first_item_has_no_previous_link(): void
    {
        $manager = $this->makeManager();
        $first = $this->makeItem();
        MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Falafel Wrap', 'slug' => 'falafel-wrap', 'base_price' => 4000, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard.menu-items.edit', $first));

        $response->assertOk();
        $response->assertDontSee('Previous item', false);
        $response->assertSee('Next item', false);
    }

    public function test_last_item_has_no_next_link(): void
    {
        $manager = $this->makeManager();
        $this->makeItem();
        $last = MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Falafel Wrap', 'slug' => 'falafel-wrap', 'base_price' => 4000, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard.menu-items.edit', $last));

        $response->assertOk();
        $response->assertSee('Previous item', false);
        $response->assertDontSee('Next item', false);
    }

    public function test_navigation_crosses_category_boundaries_in_sort_order(): void
    {
        $manager = $this->makeManager();
        $lastInFirstCategory = $this->makeItem();

        $secondCategory = Category::create(['name' => 'Drinks', 'slug' => 'drinks', 'sort_order' => 1]);
        $firstInSecondCategory = MenuItem::create([
            'category_id' => $secondCategory->id, 'name' => 'Bissap', 'slug' => 'bissap', 'base_price' => 1500,
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard.menu-items.edit', $lastInFirstCategory));
        $response->assertOk();
        $response->assertSee(route('dashboard.menu-items.edit', $firstInSecondCategory), false);
        $response->assertSee($firstInSecondCategory->name);

        $response = $this->actingAs($manager)->get(route('dashboard.menu-items.edit', $firstInSecondCategory));
        $response->assertOk();
        $response->assertSee(route('dashboard.menu-items.edit', $lastInFirstCategory), false);
        $response->assertSee($lastInFirstCategory->name);
    }

    public function test_manager_can_delete_a_menu_item(): void
    {
        $manager = $this->makeManager();
        $item = $this->makeItem();

        $this->actingAs($manager)->delete(route('dashboard.menu-items.destroy', $item))
            ->assertRedirect(route('dashboard.menu-items.index'));

        $this->assertSoftDeleted($item);
    }

    public function test_manager_can_upload_and_remove_a_menu_item_image(): void
    {
        $manager = $this->makeManager();
        $item = $this->makeItem();

        $this->actingAs($manager)
            ->post(route('dashboard.menu-items.image.update', $item), [
                'image' => UploadedFile::fake()->image('item.jpg'),
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);

        $storedPath = $item->image_path;

        $this->actingAs($manager)->delete(route('dashboard.menu-items.image.destroy', $item))->assertRedirect();

        $item->refresh();
        $this->assertNull($item->image_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_staff_cannot_upload_a_menu_item_image(): void
    {
        $staff = $this->makeStaff();
        $item = $this->makeItem();

        $this->actingAs($staff)
            ->post(route('dashboard.menu-items.image.update', $item), [
                'image' => UploadedFile::fake()->image('item.jpg'),
            ])
            ->assertForbidden();
    }
}
