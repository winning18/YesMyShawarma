<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\MenuItemRecipeItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\Shift;
use App\Models\StockItem;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecipeStockTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $loadedFries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Sides', 'slug' => 'sides']);
        $this->loadedFries = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Loaded Fries', 'slug' => 'loaded-fries', 'base_price' => 4000,
        ]);
        $this->branch->menuItems()->attach($this->loadedFries->id, ['is_available' => true]);
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
        Shift::create(['user_id' => $staff->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);

        return $staff;
    }

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    private function makeStockItem(string $name, float $quantity = 100, float $threshold = 5): StockItem
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        return app(StockService::class)->createItem(
            branchId: $this->branch->id, creator: $owner, name: $name, unit: 'pieces',
            lowStockThreshold: $threshold, initialQuantity: $quantity,
        );
    }

    private function makeRecipeItem(StockItem $stockItem, float $quantity, ?MenuItem $menuItem = null, ?Option $option = null): MenuItemRecipeItem
    {
        return MenuItemRecipeItem::create([
            'branch_id' => $this->branch->id,
            'source_type' => $menuItem ? MenuItemRecipeItem::SOURCE_MENU_ITEM : MenuItemRecipeItem::SOURCE_OPTION,
            'source_menu_item_id' => $menuItem?->id,
            'source_option_id' => $option?->id,
            'stock_item_id' => $stockItem->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int, options?: array<int, array{option_id: int, quantity: int}>}>  $lines
     */
    private function makeOrder(array $lines): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $total = 0;
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();

        foreach ($lines as $line) {
            $menuItem = MenuItem::find($line['menu_item_id']);
            $lineTotal = $menuItem->base_price * $line['quantity'];
            $total += $lineTotal;

            $orderItem = $order->items()->create([
                'menu_item_id' => $menuItem->id, 'name_snapshot' => $menuItem->name,
                'unit_price_snapshot' => $menuItem->base_price, 'quantity' => $line['quantity'], 'line_total' => $lineTotal,
            ]);

            foreach ($line['options'] ?? [] as $optionLine) {
                $option = Option::find($optionLine['option_id']);
                $orderItem->options()->create([
                    'option_id' => $option->id, 'name_snapshot' => $option->name,
                    'price_delta_snapshot' => $option->price_delta, 'quantity' => $optionLine['quantity'],
                ]);
            }
        }

        $order->update(['subtotal' => $total, 'total' => $total]);

        return $order;
    }

    public function test_accepting_an_order_deducts_the_items_recipe_scaled_by_quantity(): void
    {
        $sausage = $this->makeStockItem('Sausages', quantity: 100);
        $egg = $this->makeStockItem('Eggs', quantity: 100);
        $cheese = $this->makeStockItem('Cheese slices', quantity: 100);

        $this->makeRecipeItem($sausage, 2, menuItem: $this->loadedFries);
        $this->makeRecipeItem($egg, 2, menuItem: $this->loadedFries);
        $this->makeRecipeItem($cheese, 4, menuItem: $this->loadedFries);

        $staff = $this->makeStaff();
        $order = $this->makeOrder([['menu_item_id' => $this->loadedFries->id, 'quantity' => 3]]);

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        // 3x Loaded Fries -> 6 sausages, 6 eggs, 12 cheese slices.
        $this->assertSame('94.00', $sausage->fresh()->quantity);
        $this->assertSame('94.00', $egg->fresh()->quantity);
        $this->assertSame('88.00', $cheese->fresh()->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $sausage->id, 'type' => 'sale', 'quantity' => 6, 'actor_id' => $staff->id,
        ]);
    }

    public function test_an_item_with_no_recipe_never_touches_stock(): void
    {
        $sausage = $this->makeStockItem('Sausages', quantity: 100);
        $staff = $this->makeStaff();
        $order = $this->makeOrder([['menu_item_id' => $this->loadedFries->id, 'quantity' => 1]]);

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        $this->assertSame('100.00', $sausage->fresh()->quantity);
        $this->assertDatabaseMissing('stock_movements', ['stock_item_id' => $sausage->id, 'type' => 'sale']);
    }

    public function test_a_single_select_options_recipe_scales_with_the_order_items_quantity(): void
    {
        $spicy = $this->makeStockItem('Chilli sauce', quantity: 100);

        $spiceLevel = OptionGroup::create(['name' => 'Spice level', 'min_select' => 1, 'max_select' => 1]);
        $hot = Option::create(['option_group_id' => $spiceLevel->id, 'name' => 'Hot', 'price_delta' => 0]);
        $this->loadedFries->optionGroups()->attach($spiceLevel->id, ['sort_order' => 1]);

        $this->makeRecipeItem($spicy, 1, option: $hot);

        $staff = $this->makeStaff();
        // 3x Loaded Fries, each with "Hot" selected (single-select, quantity
        // always 1 per line) — single-select recipe consumption scales
        // with the order item's own quantity: 1 x 3 = 3.
        $order = $this->makeOrder([[
            'menu_item_id' => $this->loadedFries->id, 'quantity' => 3,
            'options' => [['option_id' => $hot->id, 'quantity' => 1]],
        ]]);

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        $this->assertSame('97.00', $spicy->fresh()->quantity);
    }

    public function test_a_multi_select_options_recipe_is_fixed_for_the_line_not_scaled_by_item_quantity(): void
    {
        $cheese = $this->makeStockItem('Cheese slices', quantity: 100);

        $extras = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);
        $extraCheese = Option::create(['option_group_id' => $extras->id, 'name' => 'Extra cheese', 'price_delta' => 300]);
        $this->loadedFries->optionGroups()->attach($extras->id, ['sort_order' => 1]);

        $this->makeRecipeItem($cheese, 1, option: $extraCheese);

        $staff = $this->makeStaff();
        // 3x Loaded Fries on one line, "Extra cheese x2" chosen once for
        // the whole line (MenuPricingService's own pricing rule) — recipe
        // consumption must follow the same rule: 1 x 2 = 2, never x3.
        $order = $this->makeOrder([[
            'menu_item_id' => $this->loadedFries->id, 'quantity' => 3,
            'options' => [['option_id' => $extraCheese->id, 'quantity' => 2]],
        ]]);

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        $this->assertSame('98.00', $cheese->fresh()->quantity);
    }

    public function test_cancelling_before_preparing_restores_the_deducted_stock(): void
    {
        $sausage = $this->makeStockItem('Sausages', quantity: 100);
        $this->makeRecipeItem($sausage, 2, menuItem: $this->loadedFries);

        $staff = $this->makeStaff();
        $order = $this->makeOrder([['menu_item_id' => $this->loadedFries->id, 'quantity' => 1]]);

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();
        $this->assertSame('98.00', $sausage->fresh()->quantity);

        // orders.void (cancel) is manager-and-above — staff never holds it.
        $manager = $this->makeManager();
        $this->actingAs($manager)->postJson(route('orders.cancel', $order), ['reason' => 'Customer changed their mind'])
            ->assertOk();

        $this->assertSame('100.00', $sausage->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $sausage->id, 'type' => 'restock', 'quantity' => 2,
        ]);
    }

    public function test_cancelling_after_preparing_has_started_does_not_restore_stock(): void
    {
        $sausage = $this->makeStockItem('Sausages', quantity: 100);
        $this->makeRecipeItem($sausage, 2, menuItem: $this->loadedFries);

        $staff = $this->makeStaff();
        $order = $this->makeOrder([['menu_item_id' => $this->loadedFries->id, 'quantity' => 1]]);

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();
        $this->actingAs($staff)->postJson(route('orders.advance', $order), ['to' => 'preparing'])->assertOk();
        $this->assertSame('98.00', $sausage->fresh()->quantity);

        $manager = $this->makeManager();
        $this->actingAs($manager)->postJson(route('orders.cancel', $order), ['reason' => 'Ran out of fries mid-prep'])
            ->assertOk();

        // Stays deducted — the food was likely actually made and wasted,
        // not un-cooked by a later cancellation.
        $this->assertSame('98.00', $sausage->fresh()->quantity);
    }

    public function test_deduction_is_allowed_to_go_negative_and_alerts_staff(): void
    {
        $sausage = $this->makeStockItem('Sausages', quantity: 1, threshold: 5);
        $this->makeRecipeItem($sausage, 2, menuItem: $this->loadedFries);

        $staff = $this->makeStaff();
        $order = $this->makeOrder([['menu_item_id' => $this->loadedFries->id, 'quantity' => 1]]);

        // Never blocked — 1 on hand, 2 needed, order still accepts fine.
        // Not asserting a specific Notifier call count here: accepting an
        // order also sends its own "order received" customer SMS through
        // the same contract, so the low-stock alert firing is verified via
        // low_stock_alerted_at instead (StockTest already covers the
        // alert's own once-per-threshold-crossing behaviour in isolation).
        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        $this->assertSame('-1.00', $sausage->fresh()->quantity);
        $this->assertNotNull($sausage->fresh()->low_stock_alerted_at);
    }

    public function test_recipe_consumption_at_a_different_branch_is_ignored(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $otherBranch);
        $otherBranchStock = app(StockService::class)->createItem(
            branchId: $otherBranch->id, creator: $owner, name: 'Sausages', unit: 'pieces',
            lowStockThreshold: 5, initialQuantity: 100,
        );
        MenuItemRecipeItem::create([
            'branch_id' => $otherBranch->id, 'source_type' => MenuItemRecipeItem::SOURCE_MENU_ITEM,
            'source_menu_item_id' => $this->loadedFries->id, 'stock_item_id' => $otherBranchStock->id, 'quantity' => 2,
        ]);

        $staff = $this->makeStaff();
        $order = $this->makeOrder([['menu_item_id' => $this->loadedFries->id, 'quantity' => 1]]);

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        // The other branch's recipe never applies to an order placed at
        // $this->branch — recipes are per-branch (payments.md).
        $this->assertSame('100.00', $otherBranchStock->fresh()->quantity);
        $this->assertDatabaseMissing('stock_movements', ['stock_item_id' => $otherBranchStock->id, 'type' => 'sale']);
    }
}
