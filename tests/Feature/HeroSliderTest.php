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

class HeroSliderTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Without this, an uploaded test image lands on the real public
        // disk — a freshly created category in an isolated test
        // transaction can easily land on the same auto-increment id as a
        // real category in dev (e.g. id 1), silently overwriting its
        // actual hero photo on disk. This bit us for real once already.
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

    public function test_manager_can_view_the_hero_slider_page(): void
    {
        $manager = $this->makeManager();
        Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);

        $this->actingAs($manager)->get(route('dashboard.hero-slider.index'))
            ->assertOk()
            ->assertSee('Shawarma');
    }

    public function test_staff_cannot_view_the_hero_slider_page(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.hero-slider.index'))->assertForbidden();
    }

    public function test_every_active_category_appears_on_the_page(): void
    {
        $manager = $this->makeManager();
        Category::create(['name' => 'Shawarma', 'slug' => 'shawarma', 'is_active' => true]);
        Category::create(['name' => 'Salads', 'slug' => 'salads', 'is_active' => true]);
        Category::create(['name' => 'Discontinued Wraps', 'slug' => 'discontinued-wraps', 'is_active' => false]);

        $response = $this->actingAs($manager)->get(route('dashboard.hero-slider.index'));

        $response->assertSee('Shawarma');
        $response->assertSee('Salads');
        $response->assertDontSee('Discontinued Wraps');
    }

    public function test_categories_are_ordered_by_sort_order(): void
    {
        $manager = $this->makeManager();
        Category::create(['name' => 'Drinks', 'slug' => 'drinks', 'sort_order' => 2]);
        Category::create(['name' => 'Shawarma', 'slug' => 'shawarma', 'sort_order' => 0]);
        Category::create(['name' => 'Burgers', 'slug' => 'burgers', 'sort_order' => 1]);

        $response = $this->actingAs($manager)->get(route('dashboard.hero-slider.index'));

        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Shawarma') < strpos($content, 'Burgers'));
        $this->assertTrue(strpos($content, 'Burgers') < strpos($content, 'Drinks'));
    }

    public function test_a_newly_created_category_is_hero_slide_eligible_with_no_code_change(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.categories.store'), [
            'name' => 'Fresh Salads',
            'is_active' => '1',
        ]);
        $category = Category::where('name', 'Fresh Salads')->first();

        // The route CategoryManagementTest already covers upload/remove
        // behaviour in detail — this only asserts the category is not
        // rejected by a leftover whitelist check.
        $this->actingAs($manager)
            ->get(route('dashboard.hero-slider.index'))
            ->assertOk()
            ->assertSee('Fresh Salads');

        $this->actingAs($manager)
            ->post(route('dashboard.categories.image.update', $category), [
                'image' => UploadedFile::fake()->image('salads.jpg'),
            ])
            ->assertRedirect();

        $this->assertNotNull($category->fresh()->hero_image_path);
    }

    public function test_categorys_tagline_is_shown_when_set(): void
    {
        $manager = $this->makeManager();
        Category::create(['name' => 'Shawarma', 'slug' => 'shawarma', 'tagline' => 'Wrapped fresh, packed with flavour.']);

        $this->actingAs($manager)->get(route('dashboard.hero-slider.index'))
            ->assertSee('Wrapped fresh, packed with flavour.');
    }
}
