<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
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

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    public function test_manager_can_view_the_categories_index(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.categories.index'))->assertOk();
    }

    public function test_staff_cannot_view_the_categories_index(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.categories.index'))->assertForbidden();
    }

    public function test_only_active_categories_appear_on_the_index(): void
    {
        $manager = $this->makeManager();
        Category::create(['name' => 'Salads', 'slug' => 'salads', 'is_active' => true]);
        Category::create(['name' => 'Discontinued Wraps', 'slug' => 'discontinued-wraps', 'is_active' => false]);

        $response = $this->actingAs($manager)->get(route('dashboard.categories.index'));

        $response->assertSee('Salads');
        $response->assertDontSee('Discontinued Wraps');
    }

    public function test_manager_can_create_a_category_with_an_auto_generated_slug(): void
    {
        $manager = $this->makeManager();

        $response = $this->actingAs($manager)->post(route('dashboard.categories.store'), [
            'name' => 'Salads',
            'sort_order' => 5,
            'is_active' => '1',
        ]);

        $category = Category::where('name', 'Salads')->first();

        $response->assertRedirect(route('dashboard.categories.edit', $category));
        $this->assertSame('salads', $category->slug);
        $this->assertSame(5, $category->sort_order);
        $this->assertTrue($category->is_active);
    }

    public function test_a_colliding_name_gets_a_numbered_slug(): void
    {
        $manager = $this->makeManager();
        Category::create(['name' => 'Salads', 'slug' => 'salads']);

        $this->actingAs($manager)->post(route('dashboard.categories.store'), ['name' => 'Salads']);

        $this->assertDatabaseHas('categories', ['name' => 'Salads', 'slug' => 'salads-2']);
    }

    public function test_manager_can_update_a_category(): void
    {
        $manager = $this->makeManager();
        $category = Category::create(['name' => 'Salads', 'slug' => 'salads']);

        $response = $this->actingAs($manager)->put(route('dashboard.categories.update', $category), [
            'name' => 'Fresh Salads',
            'slug' => 'salads',
            'sort_order' => 2,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('dashboard.categories.edit', $category));
        $this->assertSame('Fresh Salads', $category->fresh()->name);
    }

    public function test_manager_can_set_a_category_tagline(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.categories.store'), [
            'name' => 'Shawarma',
            'tagline' => 'Wrapped fresh, packed with flavour.',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Shawarma', 'tagline' => 'Wrapped fresh, packed with flavour.',
        ]);

        $category = Category::where('name', 'Shawarma')->first();

        $this->actingAs($manager)->put(route('dashboard.categories.update', $category), [
            'name' => 'Shawarma', 'slug' => $category->slug,
            'tagline' => 'Updated tagline.', 'is_active' => '1',
        ]);

        $this->assertSame('Updated tagline.', $category->fresh()->tagline);
    }

    public function test_manager_can_upload_and_remove_a_hero_slide_categorys_image(): void
    {
        $manager = $this->makeManager();
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);

        $this->actingAs($manager)
            ->post(route('dashboard.categories.image.update', $category), [
                'image' => UploadedFile::fake()->image('hero.jpg'),
            ])
            ->assertRedirect();

        $category->refresh();
        $this->assertNotNull($category->hero_image_path);
        Storage::disk('public')->assertExists($category->hero_image_path);

        $storedPath = $category->hero_image_path;

        $this->actingAs($manager)
            ->delete(route('dashboard.categories.image.destroy', $category))
            ->assertRedirect();

        $category->refresh();
        $this->assertNull($category->hero_image_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_any_category_can_receive_a_hero_image_not_just_a_fixed_set(): void
    {
        $manager = $this->makeManager();
        $category = Category::create(['name' => 'Salads', 'slug' => 'salads']);

        $this->actingAs($manager)
            ->post(route('dashboard.categories.image.update', $category), [
                'image' => UploadedFile::fake()->image('hero.jpg'),
            ])
            ->assertRedirect();

        $this->assertNotNull($category->fresh()->hero_image_path);
    }

    public function test_staff_cannot_upload_a_category_hero_image(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);

        $this->actingAs($staff)
            ->post(route('dashboard.categories.image.update', $category), [
                'image' => UploadedFile::fake()->image('hero.jpg'),
            ])
            ->assertForbidden();
    }
}
