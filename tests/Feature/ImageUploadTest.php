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

class ImageUploadTest extends TestCase
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

    public function test_owner_can_upload_and_remove_a_branch_image(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

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

    public function test_manager_can_upload_a_menu_item_image(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma',
            'base_price' => 3500,
        ]);

        $this->actingAs($manager)
            ->post(route('dashboard.menu-items.image.update', $item), [
                'image' => UploadedFile::fake()->image('item.jpg'),
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_staff_cannot_upload_a_menu_item_image(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma',
            'base_price' => 3500,
        ]);

        $this->actingAs($staff)
            ->post(route('dashboard.menu-items.image.update', $item), [
                'image' => UploadedFile::fake()->image('item.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_manager_can_upload_and_remove_a_category_hero_image(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);

        $this->actingAs($manager)
            ->post(route('dashboard.hero-images.image.update', $category), [
                'image' => UploadedFile::fake()->image('hero.jpg'),
            ])
            ->assertRedirect();

        $category->refresh();
        $this->assertNotNull($category->hero_image_path);
        Storage::disk('public')->assertExists($category->hero_image_path);

        $storedPath = $category->hero_image_path;

        $this->actingAs($manager)
            ->delete(route('dashboard.hero-images.image.destroy', $category))
            ->assertRedirect();

        $category->refresh();
        $this->assertNull($category->hero_image_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_staff_cannot_upload_a_category_hero_image(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);

        $this->actingAs($staff)
            ->post(route('dashboard.hero-images.image.update', $category), [
                'image' => UploadedFile::fake()->image('hero.jpg'),
            ])
            ->assertForbidden();
    }
}
