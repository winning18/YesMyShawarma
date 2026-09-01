<?php

namespace Tests\Feature;

use App\Contracts\Notifier;
use App\Models\Branch;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function makeStaff(?Branch $branch = null): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $branch ?? $this->branch);

        return $staff;
    }

    private function makeRider(): User
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        return $rider;
    }

    private function makeStockManager(?Branch $branch = null): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'stock_manager', $branch ?? $this->branch);

        return $manager;
    }

    private function makeItem(?Branch $branch = null, float $quantity = 10, float $threshold = 5): StockItem
    {
        $owner = $this->makeOwner();

        return app(StockService::class)->createItem(
            branchId: ($branch ?? $this->branch)->id,
            creator: $owner,
            name: 'Shawarma Bread',
            unit: 'pieces',
            lowStockThreshold: $threshold,
            initialQuantity: $quantity,
        );
    }

    public function test_owner_can_manage_stock(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('dashboard.stock.index'))->assertOk();
        $this->actingAs($owner)->get(route('dashboard.stock.create'))->assertOk();
    }

    public function test_stock_manager_can_manage_stock(): void
    {
        $manager = $this->makeStockManager();

        $this->actingAs($manager)->get(route('dashboard.stock.index'))->assertOk();
    }

    public function test_staff_can_record_a_sale_but_not_manage_stock(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->get(route('dashboard.stock.sales'))->assertOk();
        $this->actingAs($staff)->get(route('dashboard.stock.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('dashboard.stock.create'))->assertForbidden();
    }

    public function test_rider_cannot_reach_stock_at_all(): void
    {
        $rider = $this->makeRider();

        $this->actingAs($rider)->get(route('dashboard.stock.sales'))->assertForbidden();
        $this->actingAs($rider)->get(route('dashboard.stock.index'))->assertForbidden();
    }

    public function test_creating_an_item_records_an_initial_restock_movement(): void
    {
        $item = $this->makeItem(quantity: 20);

        $this->assertSame('20.00', $item->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id, 'type' => StockMovement::TYPE_RESTOCK, 'quantity' => 20,
        ]);
    }

    public function test_restocking_increases_quantity_and_records_the_actor(): void
    {
        $item = $this->makeItem(quantity: 10);
        $manager = $this->makeStockManager();

        $this->actingAs($manager)->post(route('dashboard.stock.restock', $item), [
            'quantity' => '5',
        ])->assertRedirect();

        $this->assertSame('15.00', $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id, 'type' => StockMovement::TYPE_RESTOCK,
            'quantity' => 5, 'actor_id' => $manager->id,
        ]);
    }

    public function test_recording_a_sale_decreases_quantity_and_records_the_actor(): void
    {
        $item = $this->makeItem(quantity: 10, threshold: 2);
        $staff = $this->makeStaff();

        $this->actingAs($staff)->post(route('dashboard.stock.sales.store', $item), [
            'quantity' => '3',
        ])->assertRedirect();

        $this->assertSame('7.00', $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id, 'type' => StockMovement::TYPE_SALE,
            'quantity' => 3, 'actor_id' => $staff->id,
        ]);
    }

    public function test_a_sale_cannot_exceed_remaining_stock(): void
    {
        $item = $this->makeItem(quantity: 5, threshold: 1);
        $staff = $this->makeStaff();

        $this->actingAs($staff)->post(route('dashboard.stock.sales.store', $item), [
            'quantity' => '10',
        ])->assertSessionHasErrors('quantity');

        $this->assertSame('5.00', $item->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 1); // only the initial restock
    }

    public function test_low_stock_sms_fires_once_when_crossing_the_threshold(): void
    {
        $item = $this->makeItem(quantity: 10, threshold: 5);
        $staff = $this->makeStaff();

        $this->mock(Notifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        // 10 -> 6: still at/above threshold, no alert yet.
        app(StockService::class)->recordSale($item, $staff, 4);
        $this->assertNull($item->fresh()->low_stock_alerted_at);

        // 6 -> 3: crosses below threshold, alert fires exactly once.
        app(StockService::class)->recordSale($item, $staff, 3);
        $this->assertNotNull($item->fresh()->low_stock_alerted_at);

        // 3 -> 1: still below threshold, must not fire again (mock expects ->once() total).
        app(StockService::class)->recordSale($item, $staff, 2);
    }

    public function test_restocking_above_threshold_rearms_the_low_stock_alert(): void
    {
        $item = $this->makeItem(quantity: 10, threshold: 5);
        $staff = $this->makeStaff();

        $this->mock(Notifier::class, function ($mock) {
            $mock->shouldReceive('notify')->twice();
        });

        app(StockService::class)->recordSale($item, $staff, 6); // 10 -> 4, crosses below, alerts
        $this->assertNotNull($item->fresh()->low_stock_alerted_at);

        app(StockService::class)->restock($item, $staff, 10); // 4 -> 14, back above threshold
        $this->assertNull($item->fresh()->low_stock_alerted_at);

        app(StockService::class)->recordSale($item, $staff, 12); // 14 -> 2, crosses below again, alerts again
        $this->assertNotNull($item->fresh()->low_stock_alerted_at);
    }

    public function test_a_stock_manager_at_one_branch_cannot_see_another_branchs_items(): void
    {
        $otherBranch = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $ownItem = $this->makeItem($this->branch);
        $otherItem = $this->makeItem($otherBranch);
        $manager = $this->makeStockManager($this->branch);

        $response = $this->actingAs($manager)->get(route('dashboard.stock.index'));

        $response->assertOk();
        $items = $response->viewData('items');
        $this->assertTrue($items->contains('id', $ownItem->id));
        $this->assertFalse($items->contains('id', $otherItem->id));

        $this->actingAs($manager)->get(route('dashboard.stock.edit', $otherItem))->assertNotFound();
    }

    public function test_a_pure_stock_manager_account_lands_on_the_stock_screen_after_login(): void
    {
        $manager = $this->makeStockManager();
        $manager->update(['password' => bcrypt('password')]);

        $response = $this->post(route('login'), [
            'email' => $manager->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.stock.index'));
    }
}
