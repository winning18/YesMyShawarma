<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\OrderStateMachine;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A branch without a kitchen screen (owner, 2026-11-20): POS orders need
 * one tap, Ready; website and delivery orders still need accepting.
 */
class KitchenScreenTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $item;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->item = MenuItem::create(['category_id' => $category->id, 'name' => 'Wrap', 'slug' => 'wrap', 'base_price' => 5000]);
        $this->branch->menuItems()->attach($this->item->id, ['is_available' => true]);
        $this->staff = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->staff->assignRole('staff');
    }

    private function noKitchenScreen(): void
    {
        app(SettingsService::class)->setBranchKitchenScreen($this->branch->id, false);
    }

    private function order(string $channel = 'pos', string $fulfilment = 'pickup'): Order
    {
        return app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->branch->id,
            fulfilmentType: $fulfilment, paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 1, optionQuantities: [])],
            channel: $channel,
            actorType: $channel === 'pos' ? 'staff' : null,
            actorId: $channel === 'pos' ? $this->staff->id : null,
        ));
    }

    public function test_with_a_kitchen_screen_nothing_changes(): void
    {
        $this->assertSame('paid', $this->order()->status);
    }

    public function test_a_pos_order_goes_straight_to_preparing_and_one_tap_finishes_it(): void
    {
        $this->noKitchenScreen();
        $order = $this->order();

        $this->assertSame('preparing', $order->status);
        $this->assertSame(['placed', 'no_kitchen_screen', 'no_kitchen_screen'], $order->events()->orderBy('id')->get()->map(fn ($e) => $e->meta['action'] ?? null)->all());

        $order = app(OrderStateMachine::class)->transition($order, 'ready', 'staff', $this->staff->id);
        $this->assertSame('dispatched', $order->fresh()->status, 'Ready / Done completes it');
    }

    public function test_website_orders_still_need_accepting_and_wait_for_collection(): void
    {
        $this->noKitchenScreen();
        $order = $this->order(channel: 'web');
        $this->assertSame('paid', $order->status, 'waiting to be accepted');

        $machine = app(OrderStateMachine::class);
        $machine->transition($order, 'accepted', 'staff', $this->staff->id);
        $this->assertSame('preparing', $order->fresh()->status);

        $machine->transition($order->fresh(), 'ready', 'staff', $this->staff->id);
        $this->assertSame('ready', $order->fresh()->status, 'the customer still has to collect it');
    }

    public function test_the_owner_sets_it_on_the_branch_page(): void
    {
        $owner = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $owner->assignRole('owner');

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard.branches.edit', $this->branch))->assertOk()->assertSee('This branch has a kitchen screen');

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])->put(route('dashboard.branches.update', $this->branch), [
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A', 'lat' => 5.5, 'lng' => -0.1,
            'opens_at' => '00:00', 'closes_at' => '23:59', 'is_accepting_orders' => '1', 'is_active' => '1',
            'has_kitchen_screen' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(app(SettingsService::class)->branchHasKitchenScreen($this->branch->id));
    }
}
