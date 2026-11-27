<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\HeldSale;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Printer;
use App\Models\ProductPack;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Menu\ProductCatalogService;
use App\Services\Printing\EscPosRenderer;
use App\Services\Printing\ReceiptService;
use App\Services\Reports\OrderReportService;
use App\Services\Settings\BusinessType;
use App\Services\Settings\SettingsService;
use App\Services\Shifts\ShiftService;
use App\Services\Users\PinService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The pharmacy and shop till (owner-approved 2026-11-27; plan step R3):
 * scan and search, packs, a manager-approved price change, held sales and
 * returns.
 */
class RetailTillTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $para;

    private ProductPack $box;

    private User $cashier;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(BusinessType::class)->set(BusinessType::PHARMACY);
        $this->branch = Branch::create([
            'name' => 'Madina', 'slug' => 'madina', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.6, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $category = Category::create(['name' => 'Pain relief', 'slug' => 'pain-relief']);
        $this->para = MenuItem::create(['category_id' => $category->id, 'name' => 'Paracetamol 500mg', 'slug' => 'paracetamol', 'base_price' => 50, 'sku' => 'PARA500', 'barcode' => '6151100000017', 'unit_name' => 'tablet']);
        $this->box = app(ProductCatalogService::class)->savePack($this->para, ['name' => 'Box of 100', 'units' => 100, 'price' => '40.00', 'barcode' => '6151100000031']);
        $this->branch->menuItems()->attach($this->para->id, ['is_available' => true]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('staff');
        app(PinService::class)->set($this->cashier, '1111');
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        app(PinService::class)->set($this->manager, '4321');
        $this->manager->refresh();
    }

    private function till(): static
    {
        return $this->actingAs($this->cashier)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function sell(): Order
    {
        $this->till()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash'])->assertOk();

        return Order::latest('id')->first();
    }

    public function test_a_scan_adds_the_product_or_its_pack_and_the_sale_records_the_pack(): void
    {
        $this->till()->postJson(route('dashboard.pos.scan'), ['code' => '6151100000017'])->assertOk()->assertJsonPath('added', 'Paracetamol 500mg');
        $this->till()->postJson(route('dashboard.pos.scan'), ['code' => '6151100000031'])->assertOk()->assertJsonPath('added', 'Paracetamol 500mg (Box of 100)');
        $this->till()->postJson(route('dashboard.pos.scan'), ['code' => '000'])->assertStatus(422)->assertJsonPath('errors.code.0', 'Nothing has the code 000. Check it, or search by name.');
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 30])->assertOk();

        $order = $this->sell();

        $this->assertSame('dispatched', $order->status, 'a counter sale completes as it is paid');
        $lines = $order->items()->orderBy('id')->get();
        $this->assertSame([[null, 1, 31], [$this->box->id, 100, 1]], $lines->map(fn ($l) => [$l->product_pack_id, $l->units, $l->quantity])->all());
        $this->assertSame('Paracetamol 500mg (Box of 100)', $lines[1]->name_snapshot);
        $this->assertSame(50 * 31 + 4000, $order->total);
    }

    public function test_search_finds_by_name_code_or_barcode_and_only_what_is_on_sale(): void
    {
        $this->till()->getJson(route('dashboard.pos.products', ['q' => 'para']))->assertOk()
            ->assertJsonPath('products.0.name', 'Paracetamol 500mg')->assertJsonPath('products.0.packs.0.name', 'Box of 100');
        $this->till()->getJson(route('dashboard.pos.products', ['q' => 'PARA500']))->assertJsonCount(1, 'products');

        $this->branch->menuItems()->updateExistingPivot($this->para->id, ['is_available' => false]);
        $this->till()->getJson(route('dashboard.pos.products', ['q' => 'para']))->assertJsonCount(0, 'products');
        $this->till()->postJson(route('dashboard.pos.scan'), ['code' => 'PARA500'])->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'Paracetamol 500mg isn\'t on sale at this branch right now (switched off or sold out).');
    }

    public function test_a_price_change_needs_a_managers_staff_id_and_pin_and_is_recorded(): void
    {
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'pack_id' => $this->box->id, 'quantity' => 1]);
        $line = session('pos_cart')['items'][0]['id'];
        $url = route('dashboard.pos.cart.price', $line);

        $this->till()->postJson($url, ['price' => '35', 'staff_code' => $this->manager->staff_code, 'pin' => '0000'])->assertStatus(422)->assertJsonValidationErrors('approval');
        $this->till()->postJson($url, ['price' => '35', 'staff_code' => $this->cashier->staff_code, 'pin' => '1111'])->assertStatus(422)
            ->assertJsonPath('errors.approval.0', 'That isn\'t a manager\'s Staff ID and PIN for this branch. Ask a manager to type theirs.');
        $this->till()->postJson($url, ['price' => '35', 'staff_code' => $this->manager->staff_code, 'pin' => '4321'])->assertOk()
            ->assertJsonPath('lines.0.unit_price_snapshot', 3500)->assertJsonPath('lines.0.list_price_snapshot', 4000);

        $item = $this->sell()->items->sole();
        $this->assertSame([3500, 4000, $this->manager->id], [$item->unit_price_snapshot, $item->list_price_snapshot, $item->price_changed_by]);
    }

    public function test_five_wrong_pins_lock_that_staff_id(): void
    {
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 1]);
        $url = route('dashboard.pos.cart.price', session('pos_cart')['items'][0]['id']);

        foreach (range(1, 5) as $try) {
            $this->till()->postJson($url, ['price' => '1', 'staff_code' => $this->manager->staff_code, 'pin' => '9999']);
        }
        $this->till()->postJson($url, ['price' => '1', 'staff_code' => $this->manager->staff_code, 'pin' => '4321'])->assertStatus(422)
            ->assertJsonPath('errors.approval.0', 'Too many tries. Try again in 15 minutes.');
    }

    public function test_a_managers_pin_never_signs_them_in_at_a_counter(): void
    {
        $this->assertTrue(app(PinService::class)->approvesAtTheTill($this->manager));
        $counter = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Front', 'token' => str_repeat('a', 40)]);
        $this->assertNull(app(PinService::class)->attempt($this->manager->staff_code, '4321', $counter));
    }

    public function test_a_sale_is_held_recalled_and_cleared_when_the_till_closes(): void
    {
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 2]);
        $this->till()->postJson(route('dashboard.pos.held.store'), ['label' => 'Ama'])->assertOk()->assertJsonCount(0, 'lines');
        $held = HeldSale::sole();

        $this->till()->getJson(route('dashboard.pos.held'))->assertJsonPath('held.0.label', 'Ama');
        $this->till()->postJson(route('dashboard.pos.held.recall', $held))->assertOk()->assertJsonPath('lines.0.quantity', 2);
        $this->assertSame(0, HeldSale::count());

        // Held again, then the till closes.
        $shift = Shift::create(['user_id' => $this->cashier->id, 'branch_id' => $this->branch->id, 'started_at' => now(), 'starting_cash' => 0]);
        $this->till()->postJson(route('dashboard.pos.held.store'), ['label' => null]);
        $this->assertSame(1, HeldSale::count());
        app(ShiftService::class)->end($shift, $this->cashier, 0, 0, null, [], true);
        $this->assertSame(0, HeldSale::count());
    }

    public function test_a_return_gives_back_what_was_paid_for_the_items_and_comes_off_that_till(): void
    {
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 10]);
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'pack_id' => $this->box->id, 'quantity' => 1]);
        $order = $this->sell();
        $tablets = $order->items->firstWhere('product_pack_id', null);

        $this->till()->getJson(route('dashboard.pos.returns.find', ['reference' => strtolower($order->reference)]))->assertOk()
            ->assertJsonPath('lines.0.returnable', 10)->assertJsonPath('lines.0.unit_refund', 50);

        $shift = Shift::create(['user_id' => $this->cashier->id, 'branch_id' => $this->branch->id, 'started_at' => now()->subMinute(), 'starting_cash' => 0]);
        $before = app(OrderReportService::class)->sharedTillSales($shift);
        $approval = ['staff_code' => $this->manager->staff_code, 'pin' => '4321'];

        $this->till()->postJson(route('dashboard.pos.returns.store', $order), ['quantities' => [$tablets->id => 11], 'reason' => 'Wrong one', ...$approval])->assertStatus(422)
            ->assertJsonPath('errors.lines.0', 'Only 10 of Paracetamol 500mg can still be returned. Lower the number.');
        $this->till()->postJson(route('dashboard.pos.returns.store', $order), ['quantities' => [$tablets->id => 4], 'reason' => 'Wrong one', ...$approval])->assertOk()
            ->assertJsonPath('amount', 200);

        $refund = Refund::sole();
        $this->assertSame([Refund::KIND_RETURN, Refund::STATUS_COMPLETED, $shift->id, $this->manager->id], [$refund->kind, $refund->status, $refund->shift_id, $refund->completed_by]);
        $this->assertSame([[$tablets->id, 4, 200]], $refund->items->map(fn ($i) => [$i->order_item_id, $i->quantity, $i->amount])->all());
        $this->assertTrue($order->events()->where('meta->action', 'refund_completed')->exists(), 'an order_events row');
        $this->assertSame($before - 200, app(OrderReportService::class)->sharedTillSales($shift), 'off the till');
        $this->till()->getJson(route('dashboard.pos.returns.find', ['reference' => $order->reference]))->assertJsonPath('lines.0.returnable', 6);
    }

    public function test_returns_follow_the_owners_window(): void
    {
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 1]);
        $order = $this->sell();

        app(SettingsService::class)->set(SettingsService::RETURNS_DAYS, '0');
        $this->till()->getJson(route('dashboard.pos.returns.find', ['reference' => $order->reference]))->assertStatus(422)
            ->assertJsonPath('errors.reference.0', 'This business doesn\'t take returns. The owner can change that under Settings.');

        app(SettingsService::class)->set(SettingsService::RETURNS_DAYS, '7');
        $order->forceFill(['placed_at' => now()->subDays(9)])->save();
        $this->till()->getJson(route('dashboard.pos.returns.find', ['reference' => $order->reference]))->assertStatus(422)->assertJsonValidationErrors('reference');
    }

    public function test_the_receipt_carries_its_number_as_a_barcode_and_a_scan_of_it_points_to_return(): void
    {
        $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $this->para->id, 'quantity' => 1]);
        $order = $this->sell();

        $data = app(ReceiptService::class)->receipt($order);
        $this->assertSame($order->reference, $data['barcode']);
        $this->till()->get(route('dashboard.orders.print', [$order, 'receipt']))->assertOk()->assertSee('aria-label="'.$order->reference.'"', false);

        $printer = Printer::create(['branch_id' => $this->branch->id, 'name' => 'Front', 'role' => 'receipt', 'paper_width' => 80, 'connection' => 'network', 'address' => '192.168.1.9', 'is_active' => true]);
        $bytes = app(EscPosRenderer::class)->receipt($data, $printer);
        $this->assertStringContainsString("\x1Dk\x49".chr(strlen($order->reference) + 2).'{B'.$order->reference, $bytes, 'the printer draws it (GS k, Code 128)');

        $this->till()->postJson(route('dashboard.pos.scan'), ['code' => strtolower($order->reference)])->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'That\'s receipt '.$order->reference.'. To take items back, press Return and scan it there.');

        app(BusinessType::class)->set(BusinessType::RESTAURANT);
        $this->assertNull(app(ReceiptService::class)->receipt($order->fresh())['barcode'], 'a restaurant\'s receipt is unchanged');
    }

    public function test_a_restaurant_has_none_of_it(): void
    {
        app(BusinessType::class)->set(BusinessType::RESTAURANT);

        $this->till()->getJson(route('dashboard.pos.products', ['q' => 'para']))->assertNotFound();
        $this->till()->postJson(route('dashboard.pos.scan'), ['code' => 'PARA500'])->assertNotFound();
        $this->till()->get(route('dashboard.pos.index'))->assertOk()->assertDontSee('Scan a barcode')->assertDontSee('Hold sale');
        $this->assertFalse(app(PinService::class)->approvesAtTheTill($this->manager));
    }

    public function test_the_pharmacy_till_page_shows_the_scan_box_and_buttons(): void
    {
        $this->till()->get(route('dashboard.pos.index'))->assertOk()
            ->assertSee('Scan a barcode, or type a name or product code')->assertSee('Hold sale')->assertSee('Return')->assertDontSee('Dine in');
    }
}
