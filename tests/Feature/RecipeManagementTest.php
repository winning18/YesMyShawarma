<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\StockItem;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Sides', 'slug' => 'sides']);
        $this->menuItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Loaded Fries', 'slug' => 'loaded-fries', 'base_price' => 4000,
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

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        return $staff;
    }

    private function makeStockItem(?Branch $branch = null): StockItem
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $branch ?? $this->branch);

        return app(StockService::class)->createItem(
            branchId: ($branch ?? $this->branch)->id, creator: $owner, name: 'Sausages', unit: 'pieces',
            lowStockThreshold: 5, initialQuantity: 50,
        );
    }

    public function test_manager_can_add_a_recipe_item_to_a_menu_item(): void
    {
        $manager = $this->makeManager();
        $stockItem = $this->makeStockItem();

        $this->actingAs($manager)->post(route('dashboard.menu-items.recipe.store', $this->menuItem), [
            'stock_item_id' => $stockItem->id, 'quantity' => '2.5',
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_item_recipe_items', [
            'branch_id' => $this->branch->id, 'source_type' => 'menu_item',
            'source_menu_item_id' => $this->menuItem->id, 'stock_item_id' => $stockItem->id, 'quantity' => 2.5,
        ]);
    }

    public function test_staff_cannot_manage_recipes(): void
    {
        $staff = $this->makeStaff();
        $stockItem = $this->makeStockItem();

        $this->actingAs($staff)->post(route('dashboard.menu-items.recipe.store', $this->menuItem), [
            'stock_item_id' => $stockItem->id, 'quantity' => '1',
        ])->assertForbidden();
    }

    public function test_cannot_add_a_recipe_item_using_another_branchs_stock_item(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $manager = $this->makeManager();
        $otherBranchStock = $this->makeStockItem($otherBranch);

        $this->actingAs($manager)->post(route('dashboard.menu-items.recipe.store', $this->menuItem), [
            'stock_item_id' => $otherBranchStock->id, 'quantity' => '1',
        ])->assertSessionHasErrors('stock_item_id');

        $this->assertDatabaseCount('menu_item_recipe_items', 0);
    }

    public function test_cannot_add_the_same_stock_item_twice_to_the_same_recipe(): void
    {
        $manager = $this->makeManager();
        $stockItem = $this->makeStockItem();

        $this->actingAs($manager)->post(route('dashboard.menu-items.recipe.store', $this->menuItem), [
            'stock_item_id' => $stockItem->id, 'quantity' => '1',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('dashboard.menu-items.recipe.store', $this->menuItem), [
            'stock_item_id' => $stockItem->id, 'quantity' => '2',
        ])->assertSessionHasErrors('stock_item_id');

        $this->assertDatabaseCount('menu_item_recipe_items', 1);
    }

    public function test_manager_can_remove_a_recipe_item(): void
    {
        $manager = $this->makeManager();
        $stockItem = $this->makeStockItem();

        $recipeItem = MenuItemRecipeItem::create([
            'branch_id' => $this->branch->id, 'source_type' => MenuItemRecipeItem::SOURCE_MENU_ITEM,
            'source_menu_item_id' => $this->menuItem->id, 'stock_item_id' => $stockItem->id, 'quantity' => 1,
        ]);

        $this->actingAs($manager)->delete(route('dashboard.recipe.destroy', $recipeItem))->assertRedirect();

        $this->assertDatabaseMissing('menu_item_recipe_items', ['id' => $recipeItem->id]);
    }

    public function test_manager_can_add_a_recipe_item_to_an_option(): void
    {
        $manager = $this->makeManager();
        $stockItem = $this->makeStockItem();

        $extras = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);
        $cheese = Option::create(['option_group_id' => $extras->id, 'name' => 'Extra cheese', 'price_delta' => 300]);

        $this->actingAs($manager)->post(route('dashboard.options.recipe.store', $cheese), [
            'stock_item_id' => $stockItem->id, 'quantity' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_item_recipe_items', [
            'branch_id' => $this->branch->id, 'source_type' => 'option',
            'source_option_id' => $cheese->id, 'stock_item_id' => $stockItem->id, 'quantity' => 1,
        ]);
    }

    public function test_menu_item_edit_page_shows_the_recipe_section(): void
    {
        $manager = $this->makeManager();
        $stockItem = $this->makeStockItem();

        MenuItemRecipeItem::create([
            'branch_id' => $this->branch->id, 'source_type' => MenuItemRecipeItem::SOURCE_MENU_ITEM,
            'source_menu_item_id' => $this->menuItem->id, 'stock_item_id' => $stockItem->id, 'quantity' => 2,
        ]);

        $this->actingAs($manager)->get(route('dashboard.menu-items.edit', $this->menuItem))
            ->assertOk()
            ->assertSee('Recipe (stock used)')
            ->assertSee('Sausages');
    }
}
