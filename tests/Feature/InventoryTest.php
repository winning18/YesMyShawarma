<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ProductPack;
use App\Models\PurchaseOrder;
use App\Models\RefundItem;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Menu\ProductCatalogService;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\OrderStateMachine;
use App\Services\Settings\BusinessType;
use App\Services\Stock\InventoryReportService;
use App\Services\Stock\ProductStockService;
use App\Services\Stock\StockService;
use App\Services\Stock\StockTransferService;
use App\Services\Users\PinService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A pharmacy's or shop's stock (owner-approved 2026-11-27; plan step R4):
 * sales take it off, deliveries add it, counts correct it, transfers move
 * it, returns put it back unless damaged, and the reports.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $other;

    private MenuItem $para;

    private ProductPack $box;

    private User $cashier;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(BusinessType::class)->set(BusinessType::PHARMACY);
        $make = fn (string $name) => Branch::create(['name' => $name, 'slug' => strtolower($name), 'phone' => '+233200000001', 'address' => 'A', 'lat' => 5.6, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59']);
        $this->branch = $make('Madina');
        $this->other = $make('Osu');
        $category = Category::create(['name' => 'Pain relief', 'slug' => 'pain-relief']);
        $this->para = MenuItem::create(['category_id' => $category->id, 'name' => 'Paracetamol 500mg', 'slug' => 'paracetamol', 'base_price' => 50, 'cost_price' => 30, 'sku' => 'PARA500', 'unit_name' => 'tablet']);
        $this->box = app(ProductCatalogService::class)->savePack($this->para, ['name' => 'Box of 100', 'units' => 100, 'price' => '40.00', 'cost_price' => '25.00']);
        foreach ([$this->branch, $this->other] as $b) {
            $b->menuItems()->attach($this->para->id, ['is_available' => true]);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('staff');
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        app(PinService::class)->set($this->owner, '4321');
        $this->owner->refresh();
    }

    private function onHand(?Branch $branch = null): float
    {
        return app(ProductStockService::class)->onHand(($branch ?? $this->branch)->id, [$this->para->id])[$this->para->id];
    }

    private function stockUp(float $units, ?Branch $branch = null): void
    {
        $item = app(ProductStockService::class)->itemFor($this->para, ($branch ?? $this->branch)->id, $this->owner);
        app(StockService::class)->record($item, $this->owner, StockMovement::TYPE_RECEIVED, $units);
    }

    private function till(): static
    {
        return $this->actingAs($this->cashier)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function asOwner(?Branch $branch = null): static
    {
        return $this->actingAs($this->owner)->withSession(['current_branch_id' => ($branch ?? $this->branch)->id]);
    }

    public function test_a_sale_takes_its_units_off_and_keeps_its_cost(): void
    {
        $this->stockUp(500);
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'pack_id' => $this->box->id, 'quantity' => 2]);
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 10]);
        $this->till()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash'])->assertOk();

        $this->assertSame(500.0 - 200 - 10, $this->onHand());
        $order = Order::sole();
        $this->assertEqualsCanonicalizing([2500, 30], $order->items->pluck('cost_snapshot')->all(), 'the pack\'s cost and the tablet\'s');
        $this->assertTrue(StockMovement::where('source_type', 'order')->where('source_id', $order->id)->where('type', 'sale')->where('quantity', 210)->exists());
    }

    public function test_the_till_warns_but_sells_when_stock_runs_short(): void
    {
        $this->stockUp(5);
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 8])->assertOk()
            ->assertJsonPath('stock_warning', 'Stock shows 5 Paracetamol 500mg, and this sale has 8. Sold anyway; check the shelf or count it.');
        $this->till()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash'])->assertOk();
        $this->assertSame(-3.0, $this->onHand());
    }

    public function test_a_website_order_cancelled_before_it_is_handed_over_puts_stock_back(): void
    {
        $this->stockUp(50);
        $order = app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->branch->id, fulfilmentType: 'pickup', paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->para->id, quantity: 4)], channel: 'web',
        ));
        $machine = app(OrderStateMachine::class);
        $machine->transition($order, 'accepted', 'staff', $this->cashier->id);
        $this->assertSame(46.0, $this->onHand());

        // No kitchen: accepted goes straight on to preparing; cancelled from there, the goods go back.
        $this->assertSame('preparing', $order->fresh()->status);
        $machine->transition($order->fresh(), 'cancelled', 'manager', $this->owner->id, ['reason' => 'Customer changed mind'], cancellationReason: 'Customer changed mind');
        $this->assertSame(50.0, $this->onHand());
    }

    public function test_a_return_goes_back_on_the_shelf_unless_damaged(): void
    {
        $this->stockUp(100);
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 10]);
        $this->till()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash']);
        $order = Order::sole();
        $line = $order->items->sole();
        $approval = ['staff_code' => $this->owner->staff_code, 'pin' => '4321', 'reason' => 'Wrong one'];

        $this->till()->postJson(route('dashboard.pos.returns.store', $order), ['quantities' => [$line->id => 3], ...$approval])->assertOk();
        $this->assertSame(93.0, $this->onHand(), '3 back on the shelf');
        $this->till()->postJson(route('dashboard.pos.returns.store', $order), ['quantities' => [$line->id => 2], 'damaged' => [$line->id], ...$approval])->assertOk();
        $this->assertSame(93.0, $this->onHand(), 'damaged ones stay out');
        $this->assertSame([true, false], RefundItem::orderBy('id')->pluck('restocked')->all());
    }

    public function test_a_purchase_order_is_received_in_parts_and_updates_the_cost(): void
    {
        $supplier = Supplier::create(['name' => 'Ernest Chemists']);
        $this->asOwner()->post(route('dashboard.inventory.purchase-orders.store'), ['supplier_id' => $supplier->id, 'lines' => [
            ['menu_item_id' => $this->para->id, 'pack_id' => $this->box->id, 'quantity' => 5, 'unit_cost' => '22.00'],
            ['menu_item_id' => $this->para->id, 'pack_id' => '', 'quantity' => 50, 'unit_cost' => '0.28'],
        ]])->assertRedirect();
        $po = PurchaseOrder::sole();
        $this->assertSame([PurchaseOrder::DRAFT, 5 * 2200 + 50 * 28], [$po->status, $po->total]);
        $this->asOwner()->get(route('dashboard.inventory.purchase-orders.show', $po))->assertOk()->assertSee('Paracetamol 500mg (Box of 100)');

        $this->asOwner()->post(route('dashboard.inventory.purchase-orders.ordered', $po));
        [$boxLine, $tabLine] = $po->items()->orderBy('id')->get()->all();

        $this->asOwner()->post(route('dashboard.inventory.purchase-orders.receive', $po), ['received' => [$boxLine->id => 9]])->assertSessionHasErrors('received');
        $this->asOwner()->post(route('dashboard.inventory.purchase-orders.receive', $po), ['received' => [$boxLine->id => 3, $tabLine->id => 0]])->assertSessionHasNoErrors();
        $this->assertSame(300.0, $this->onHand());
        $this->assertSame(PurchaseOrder::PART_RECEIVED, $po->fresh()->status);
        $this->assertSame([2200, 22], [$this->box->fresh()->cost_price, $this->para->fresh()->cost_price], 'the latest cost becomes the cost price');

        $this->asOwner()->post(route('dashboard.inventory.purchase-orders.receive', $po), ['received' => [$boxLine->id => 2, $tabLine->id => 50]]);
        $this->assertSame(550.0, $this->onHand());
        $this->assertSame(PurchaseOrder::RECEIVED, $po->fresh()->status);
    }

    public function test_a_delivery_with_no_order_adds_stock_at_once(): void
    {
        $this->asOwner()->post(route('dashboard.inventory.purchase-orders.store'), ['receive_now' => 1, 'lines' => [
            ['menu_item_id' => $this->para->id, 'quantity' => 40, 'unit_cost' => '0.30'],
        ]])->assertRedirect();

        $this->assertSame(40.0, $this->onHand());
        $this->assertSame(PurchaseOrder::RECEIVED, PurchaseOrder::sole()->status);
    }

    public function test_a_count_corrects_stock_by_what_was_counted_allowing_for_sales_meanwhile(): void
    {
        $this->stockUp(100);
        $this->asOwner()->post(route('dashboard.inventory.counts.store'))->assertRedirect();
        $count = StockCount::sole();
        $line = $count->items()->sole();
        $this->assertSame('100.00', (string) $line->expected);
        $this->asOwner()->post(route('dashboard.inventory.counts.store'))->assertSessionHasErrors('count');

        // 10 sold while counting; 85 on the shelf when counted (so 5 were missing).
        $item = StockItem::where('menu_item_id', $this->para->id)->sole();
        app(StockService::class)->record($item, $this->cashier, StockMovement::TYPE_SALE, -10);
        $this->asOwner()->get(route('dashboard.inventory.counts.show', $count))->assertOk()->assertSee('Paracetamol 500mg');
        $this->asOwner()->put(route('dashboard.inventory.counts.update', $count), ['counted' => [$line->id => 85], 'finish' => 1])->assertRedirect()
            ->assertSessionHas('status', 'Count finished: 1 product corrected.');

        $this->assertSame(75.0, $this->onHand(), '90 less the 15 short');
        $this->assertSame(StockCount::FINISHED, $count->fresh()->status);
        $this->assertTrue(StockMovement::where('type', 'count')->where('quantity', -15)->exists());
    }

    public function test_a_transfer_leaves_when_sent_and_arrives_when_confirmed(): void
    {
        $this->stockUp(100);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->other->id);

        $this->asOwner()->post(route('dashboard.inventory.transfers.store'), ['to_branch_id' => $this->other->id, 'lines' => [['menu_item_id' => $this->para->id, 'units' => 30]]])->assertRedirect();
        $transfer = StockTransfer::sole();
        $this->assertSame([70.0, 0.0], [$this->onHand(), $this->onHand($this->other)]);

        // Only the receiving branch confirms.
        $line = $transfer->items()->sole();
        $this->asOwner()->post(route('dashboard.inventory.transfers.receive', $transfer), ['received' => [$line->id => 28]])->assertNotFound();
        $this->asOwner($this->other)->post(route('dashboard.inventory.transfers.receive', $transfer), ['received' => [$line->id => 28]])->assertRedirect();

        $this->assertSame([70.0, 28.0], [$this->onHand(), $this->onHand($this->other)]);
        $this->assertSame([StockTransfer::RECEIVED, 28], [$transfer->fresh()->status, $line->fresh()->received_units]);
    }

    public function test_a_transfer_cancelled_before_it_arrives_comes_back(): void
    {
        $this->stockUp(100);
        $transfer = app(StockTransferService::class)->send($this->branch->id, $this->other->id, [['menu_item_id' => $this->para->id, 'units' => 30]], $this->owner);
        $this->asOwner()->post(route('dashboard.inventory.transfers.cancel', $transfer))->assertRedirect();
        $this->assertSame(100.0, $this->onHand());
    }

    public function test_the_reports_show_profit_stock_value_and_what_is_not_selling(): void
    {
        $this->stockUp(300);
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 10]);
        $this->till()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash']);

        $row = app(InventoryReportService::class)->profitByProduct($this->branch->id, now()->subDay(), now()->addDay())->sole();
        $this->assertSame([10, 500, 300, 200], [$row->units, $row->sales, $row->cost, $row->profit]);
        $this->assertSame(290 * 30, app(InventoryReportService::class)->stockValue($this->branch->id)->sole()->value);
        $this->assertCount(0, app(InventoryReportService::class)->notSelling($this->branch->id, 30));

        $this->stockUp(10, $this->other);
        $this->assertCount(1, app(InventoryReportService::class)->notSelling($this->other->id, 30), 'in stock at Osu, never sold there');

        $this->asOwner()->get(route('dashboard.inventory.reports'))->assertOk()->assertSee('Profit by product')->assertSee('GH₵2.00');
    }

    public function test_the_stock_pages_open_for_whoever_manages_stock(): void
    {
        foreach (['index', 'purchase-orders.index', 'purchase-orders.create', 'suppliers.index', 'suppliers.create', 'counts.index', 'transfers.index', 'transfers.create', 'reports'] as $page) {
            $this->asOwner()->get(route('dashboard.inventory.'.$page))->assertOk();
        }
        $this->asOwner()->get(route('dashboard.inventory.history', $this->para))->assertOk();
        $this->asOwner()->getJson(route('dashboard.inventory.products', ['q' => 'para']))->assertJsonPath('products.0.packs.0.name', 'Box of 100');
        $this->till()->get(route('dashboard.inventory.index'))->assertForbidden();
    }

    public function test_with_no_branch_chosen_the_stock_forms_ask_for_one_first(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard.inventory.purchase-orders.create'))->assertRedirect(route('branches.select'));
        $this->actingAs($this->owner)->get(route('dashboard.inventory.transfers.create'))->assertRedirect(route('branches.select'));
    }

    public function test_a_restaurant_has_none_of_it(): void
    {
        app(BusinessType::class)->set(BusinessType::RESTAURANT);

        $this->asOwner()->get(route('dashboard.inventory.index'))->assertNotFound();
        $this->till()->postJson(route('dashboard.pos.cart.add'), ['menu_item_id' => $this->para->id, 'quantity' => 1, 'option_ids' => []]);
        $this->till()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash'])->assertOk();
        app(OrderStateMachine::class)->transition(Order::sole(), 'accepted', 'staff', $this->cashier->id);

        $this->assertSame(0, StockItem::withoutGlobalScopes()->whereNotNull('menu_item_id')->count(), 'no product stock for a restaurant');
    }
}
