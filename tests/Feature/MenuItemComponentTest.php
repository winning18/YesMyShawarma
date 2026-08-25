<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MenuItemComponentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    private MenuItem $chickenShawarma;

    private MenuItem $signature;

    private Option $cheese;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);

        $this->chickenShawarma = MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->signature = MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Signature (Chicken, Cheese & Sausage)', 'slug' => 'signature', 'base_price' => 7000,
        ]);

        $extras = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 6]);
        $this->cheese = Option::create(['option_group_id' => $extras->id, 'name' => 'Cheese', 'price_delta' => 1000]);
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

    public function test_manager_can_add_a_base_component(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.menu-items.components.store', $this->signature), [
            'component_type' => 'base',
            'component_menu_item_id' => $this->chickenShawarma->id,
            'quantity' => 1,
        ])->assertRedirect(route('dashboard.menu-items.edit', $this->signature));

        $this->assertDatabaseHas('menu_item_components', [
            'menu_item_id' => $this->signature->id,
            'component_type' => 'base',
            'component_menu_item_id' => $this->chickenShawarma->id,
            'component_option_id' => null,
            'quantity' => 1,
        ]);
    }

    public function test_manager_can_add_a_modifier_component(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.menu-items.components.store', $this->signature), [
            'component_type' => 'modifier',
            'component_option_id' => $this->cheese->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_item_components', [
            'menu_item_id' => $this->signature->id,
            'component_type' => 'modifier',
            'component_option_id' => $this->cheese->id,
            'component_menu_item_id' => null,
            'quantity' => 1,
        ]);
    }

    public function test_base_component_requires_a_menu_item(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.menu-items.components.store', $this->signature), [
            'component_type' => 'base',
            'quantity' => 1,
        ])->assertSessionHasErrors('component_menu_item_id');
    }

    public function test_manager_can_remove_a_component(): void
    {
        $manager = $this->makeManager();
        $component = $this->signature->components()->create([
            'component_type' => 'base', 'component_menu_item_id' => $this->chickenShawarma->id, 'quantity' => 1,
        ]);

        $this->actingAs($manager)
            ->delete(route('dashboard.menu-items.components.destroy', [$this->signature, $component]))
            ->assertRedirect();

        $this->assertDatabaseMissing('menu_item_components', ['id' => $component->id]);
    }

    public function test_a_component_belonging_to_another_item_cannot_be_removed_through_this_item(): void
    {
        $manager = $this->makeManager();
        $otherItem = MenuItem::create([
            'category_id' => $this->category->id, 'name' => 'Mamies', 'slug' => 'mamies', 'base_price' => 6000,
        ]);
        $component = $otherItem->components()->create([
            'component_type' => 'base', 'component_menu_item_id' => $this->chickenShawarma->id, 'quantity' => 1,
        ]);

        $this->actingAs($manager)
            ->delete(route('dashboard.menu-items.components.destroy', [$this->signature, $component]))
            ->assertNotFound();

        $this->assertDatabaseHas('menu_item_components', ['id' => $component->id]);
    }

    public function test_staff_cannot_manage_components(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->post(route('dashboard.menu-items.components.store', $this->signature), [
            'component_type' => 'base',
            'component_menu_item_id' => $this->chickenShawarma->id,
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_edit_page_shows_configured_components(): void
    {
        $manager = $this->makeManager();
        $this->signature->components()->create([
            'component_type' => 'base', 'component_menu_item_id' => $this->chickenShawarma->id, 'quantity' => 1,
        ]);
        $this->signature->components()->create([
            'component_type' => 'modifier', 'component_option_id' => $this->cheese->id, 'quantity' => 1,
        ]);

        $this->actingAs($manager)->get(route('dashboard.menu-items.edit', $this->signature))
            ->assertOk()
            ->assertSee('Chicken Shawarma')
            ->assertSee('Cheese');
    }
}
