<?php

namespace Tests\Feature;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItemBatch;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Settings\BusinessType;
use App\Services\Stock\BatchService;
use App\Services\Stock\ControlledRegisterService;
use App\Services\Stock\ProductStockService;
use App\Services\Stock\PurchaseOrderService;
use App\Services\Users\PinService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The pharmacy layer (owner-approved 2026-11-27; plan step R5): medicines,
 * batches and expiry, a manager's approval and the prescription at the
 * till, the controlled drugs register, and no prescription medicine online.
 */
class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    private MenuItem $para;

    private MenuItem $amox;

    private MenuItem $tramadol;

    private User $cashier;

    private User $manager;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(BusinessType::class)->set(BusinessType::PHARMACY);
        $this->branch = Branch::create(['name' => 'Madina', 'slug' => 'madina', 'phone' => '+233200000001', 'address' => 'A', 'lat' => 5.6, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59']);
        $this->category = Category::create(['name' => 'Medicines', 'slug' => 'medicines']);
        $make = fn (string $name, string $class, int $price) => tap(MenuItem::create(['category_id' => $this->category->id, 'name' => $name, 'slug' => str($name)->slug(), 'base_price' => $price, 'unit_name' => 'tablet', 'medicine_class' => $class]),
            fn ($m) => $this->branch->menuItems()->attach($m->id, ['is_available' => true]));
        $this->para = $make('Paracetamol 500mg', MenuItem::OTC, 50);
        $this->amox = $make('Amoxicillin 500mg', MenuItem::PRESCRIPTION_ONLY, 200);
        $this->tramadol = $make('Tramadol 50mg', MenuItem::CONTROLLED, 300);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('staff');
        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
        app(PinService::class)->set($this->manager, '4321');
        $this->manager->refresh();
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
    }

    private function till(): static
    {
        return $this->actingAs($this->cashier)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function receive(MenuItem $product, int $units, string $expiry, string $batch = 'B1'): void
    {
        app(PurchaseOrderService::class)->receiveWithoutOrder($this->branch->id, null, [
            ['menu_item_id' => $product->id, 'quantity' => $units, 'unit_cost' => '0.10', 'batch_number' => $batch, 'expiry_date' => $expiry],
        ], $this->owner);
    }

    private function add(MenuItem $product, int $quantity = 1)
    {
        return $this->till()->postJson(route('dashboard.pos.add-product'), ['menu_item_id' => $product->id, 'quantity' => $quantity]);
    }

    private function place()
    {
        return $this->till()->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash']);
    }

    private function prescription(): array
    {
        return ['patient_name' => 'Kwame Asante', 'prescriber_name' => 'Dr A. Mensah', 'prescriber_facility' => 'Ridge Hospital', 'issued_on' => now()->subDay()->toDateString(), 'prescription_number' => 'RX-778'];
    }

    public function test_a_medicine_is_received_with_its_batch_and_expiry(): void
    {
        $po = app(PurchaseOrderService::class)->create($this->branch->id, null, [['menu_item_id' => $this->para->id, 'quantity' => 100, 'unit_cost' => '0.20']], $this->owner);
        $line = $po->items->sole();

        $this->expectsValidation(fn () => app(PurchaseOrderService::class)->receive($po, [$line->id => 100], $this->owner), 'enter its expiry date');
        $this->expectsValidation(fn () => app(PurchaseOrderService::class)->receive($po, [$line->id => 100], $this->owner, [$line->id => ['batch_number' => 'X', 'expiry_date' => now()->subDay()->toDateString()]]), 'already passed');

        app(PurchaseOrderService::class)->receive($po, [$line->id => 100], $this->owner, [$line->id => ['batch_number' => 'PX12', 'expiry_date' => now()->addYear()->toDateString()]]);
        $batch = StockBatch::sole();
        $this->assertSame(['PX12', '100.00'], [$batch->batch_number, (string) $batch->quantity]);
        $this->assertSame(PurchaseOrder::RECEIVED, $po->fresh()->status);
    }

    public function test_the_batch_that_expires_first_is_sold_first_and_recorded(): void
    {
        $this->receive($this->para, 50, now()->addYear()->toDateString(), 'LATE');
        $this->receive($this->para, 10, now()->addMonth()->toDateString(), 'EARLY');

        $this->add($this->para, 15);
        $this->place()->assertOk();

        $this->assertEquals(['EARLY' => '0.00', 'LATE' => '45.00'], StockBatch::pluck('quantity', 'batch_number')->map(fn ($q) => (string) $q)->all());
        $this->assertEqualsCanonicalizing(['10.00', '5.00'], OrderItemBatch::pluck('units')->map(fn ($u) => (string) $u)->all(), 'which batches the sale used, for recalls');
    }

    public function test_expired_stock_is_never_sold_and_is_written_off(): void
    {
        $this->receive($this->para, 20, now()->addMonth()->toDateString());
        StockBatch::query()->update(['expiry_date' => now()->subDay()->toDateString()]);

        $this->add($this->para)->assertStatus(422)->assertJsonPath('errors.code.0', 'All the Paracetamol 500mg here has expired, so it can\'t be sold. Write it off under Stock → Expiry.');

        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branch->id])->get(route('dashboard.inventory.expiry'))->assertOk()->assertSee('(expired)');
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branch->id])->post(route('dashboard.inventory.batches.write-off', StockBatch::sole()))->assertRedirect();
        $this->assertSame(0.0, app(ProductStockService::class)->onHand($this->branch->id, [$this->para->id])[$this->para->id]);
        $this->assertTrue(StockMovement::where('type', 'expired')->where('quantity', 20)->exists());
    }

    public function test_a_prescription_only_sale_needs_the_prescription_and_a_managers_pin(): void
    {
        $this->receive($this->amox, 30, now()->addYear()->toDateString());
        $this->add($this->amox, 10)->assertJsonPath('pharmacy.needs_prescription', true)->assertJsonPath('pharmacy.approved', false);

        $this->place()->assertStatus(422)->assertJsonPath('errors.approval.0', 'This sale has prescription-only medicine: record the prescription and have a manager approve it first.');

        $check = route('dashboard.pos.pharmacy-check');
        $this->till()->postJson($check, ['staff_code' => $this->manager->staff_code, 'pin' => '4321', 'prescription' => ['patient_name' => 'Kwame']])->assertStatus(422)->assertJsonValidationErrors('prescription.prescriber_name');
        $this->till()->postJson($check, ['staff_code' => $this->cashier->staff_code ?? 'X', 'pin' => '1111', 'prescription' => $this->prescription()])->assertStatus(422)->assertJsonValidationErrors('approval');
        $this->till()->postJson($check, ['staff_code' => $this->manager->staff_code, 'pin' => '4321', 'prescription' => $this->prescription()])->assertOk()->assertJsonPath('pharmacy.approved', true);

        $this->place()->assertOk();
        $order = Order::sole();
        $prescription = Prescription::sole();
        $this->assertSame([$order->id, 'Kwame Asante', 'Dr A. Mensah', $this->manager->id], [$prescription->order_id, $prescription->patient_name, $prescription->prescriber_name, $prescription->approved_by]);
        $this->assertTrue($order->events()->where('meta->action', 'pharmacy_check')->exists(), 'an order_events row names who approved');
        $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branch->id])->get(route('dashboard.orders.show', $order))->assertSee('Kwame Asante');
    }

    public function test_an_approval_covers_only_the_items_it_was_given_for(): void
    {
        $this->receive($this->amox, 30, now()->addYear()->toDateString());
        $this->receive($this->tramadol, 30, now()->addYear()->toDateString());
        $this->add($this->amox);
        $this->till()->postJson(route('dashboard.pos.pharmacy-check'), ['staff_code' => $this->manager->staff_code, 'pin' => '4321', 'prescription' => $this->prescription()])->assertOk();

        $this->add($this->tramadol)->assertJsonPath('pharmacy.approved', false);
        $this->place()->assertStatus(422);
    }

    public function test_an_otc_medicine_sells_freely(): void
    {
        $this->receive($this->para, 30, now()->addYear()->toDateString());
        $this->add($this->para)->assertJsonPath('pharmacy.needs_approval', false);
        $this->place()->assertOk();
    }

    public function test_the_register_lists_each_controlled_entry_with_its_prescription_and_balance(): void
    {
        $this->receive($this->tramadol, 100, now()->addYear()->toDateString(), 'TR9');
        $this->add($this->tramadol, 6);
        $this->till()->postJson(route('dashboard.pos.pharmacy-check'), ['staff_code' => $this->manager->staff_code, 'pin' => '4321', 'prescription' => $this->prescription()]);
        $this->place()->assertOk();

        $register = app(ControlledRegisterService::class)->entries($this->tramadol, $this->branch->id, now()->subDay(), now()->addDay());
        $this->assertSame(0.0, $register['opening']);
        $this->assertSame([['Received', 100.0, null, 100.0], ['Sold', null, 6.0, 94.0]], array_map(fn ($r) => [$r['what'], $r['in'], $r['out'], $r['balance']], $register['rows']));
        $this->assertStringContainsString('Patient: Kwame Asante · Prescriber: Dr A. Mensah (Ridge Hospital)', $register['rows'][1]['details']);
        $this->assertSame($this->manager->name, $register['rows'][1]['by']);

        $this->actingAs($this->manager)->withSession(['current_branch_id' => $this->branch->id])->get(route('dashboard.inventory.register'))->assertOk()->assertSee('Tramadol 50mg')->assertSee('Kwame Asante');
    }

    public function test_prescription_medicine_is_not_sold_online(): void
    {
        $this->post(route('cart.add'), ['branch_id' => $this->branch->id, 'menu_item_id' => $this->amox->id, 'quantity' => 1])
            ->assertSessionHasErrors(['menu_item_id' => 'Amoxicillin 500mg is sold only against a prescription. Visit us with it, and our pharmacist will help.']);
        $this->expectException(OrderPlacementException::class);
        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->branch->id, fulfilmentType: 'pickup', paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->amox->id, quantity: 1)], channel: 'web',
        ));
    }

    public function test_a_returned_medicine_goes_back_into_its_batch_and_a_count_takes_the_earliest_first(): void
    {
        $this->receive($this->para, 10, now()->addMonth()->toDateString(), 'EARLY');
        $this->receive($this->para, 10, now()->addYear()->toDateString(), 'LATE');
        $this->add($this->para, 4);
        $this->place();
        $order = Order::sole();
        $this->till()->postJson(route('dashboard.pos.returns.store', $order), ['quantities' => [$order->items->sole()->id => 2], 'reason' => 'Not needed', 'staff_code' => $this->manager->staff_code, 'pin' => '4321'])->assertOk();
        $this->assertSame('8.00', (string) StockBatch::where('batch_number', 'EARLY')->value('quantity'));

        $item = app(ProductStockService::class)->itemFor($this->para, $this->branch->id, $this->owner);
        app(BatchService::class)->adjust($item, -5);
        $this->assertEquals(['EARLY' => '3.00', 'LATE' => '10.00'], StockBatch::pluck('quantity', 'batch_number')->map(fn ($q) => (string) $q)->all());
    }

    public function test_the_product_form_and_import_take_medicine_details_at_a_pharmacy_only(): void
    {
        $owner = $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branch->id]);
        $owner->get(route('dashboard.menu-items.create'))->assertOk()->assertSee('Medicine class')->assertSee('FDA Ghana registration no.');
        $owner->post(route('dashboard.menu-items.store'), ['category_id' => $this->category->id, 'name' => 'Ibuprofen 400mg', 'base_price' => '1.00', 'is_active' => '1', 'medicine_class' => 'pharmacy', 'generic_name' => 'Ibuprofen', 'strength' => '400mg', 'dosage_form' => 'Tablet', 'fda_number' => 'FDA/SD.123'])->assertRedirect();
        $this->assertSame(['pharmacy', 'Ibuprofen', 'FDA/SD.123'], MenuItem::where('name', 'Ibuprofen 400mg')->first(['medicine_class', 'generic_name', 'fda_number'])->only(['medicine_class', 'generic_name', 'fda_number']) ? array_values(MenuItem::where('name', 'Ibuprofen 400mg')->first()->only(['medicine_class', 'generic_name', 'fda_number'])) : []);

        $path = tempnam(sys_get_temp_dir(), 'meds').'.csv';
        file_put_contents($path, "Name,Category,Price,Class,Generic name,Strength\nCipro 500mg,Antibiotics,3.00,Prescription only,Ciprofloxacin,500mg\nVitamin C,Vitamins,1.00,banana,,\n");
        $this->artisan('expresspos:import-products', ['file' => $path])->assertFailed()->expectsOutputToContain('Row 3: the Class "banana"');
        file_put_contents($path, "Name,Category,Price,Class,Generic name,Strength\nCipro 500mg,Antibiotics,3.00,Prescription only,Ciprofloxacin,500mg\n");
        $this->artisan('expresspos:import-products', ['file' => $path])->assertSuccessful();
        $this->assertSame('pom', MenuItem::where('name', 'Cipro 500mg')->value('medicine_class'));

        app(BusinessType::class)->set(BusinessType::SHOP);
        $owner->get(route('dashboard.menu-items.create'))->assertOk()->assertDontSee('Medicine class');
    }

    public function test_managers_manage_stock_at_a_pharmacy_or_shop_but_not_at_a_restaurant(): void
    {
        $asManager = fn () => $this->actingAs($this->manager)->withSession(['current_branch_id' => $this->branch->id]);
        $asManager()->get(route('dashboard.inventory.index'))->assertOk();
        $this->actingAs($this->cashier)->withSession(['current_branch_id' => $this->branch->id])->get(route('dashboard.inventory.index'))->assertForbidden();

        // A restaurant's managers stay without stock access (its Recipes and stock pages).
        app(BusinessType::class)->set(BusinessType::RESTAURANT);
        $asManager()->get(route('dashboard.stock.index'))->assertForbidden();
    }

    public function test_the_owner_is_texted_about_expiring_batches(): void
    {
        $this->owner->update(['phone' => '+233244000000']);
        $this->receive($this->para, 10, now()->addDays(20)->toDateString());

        $this->artisan('expresspos:expiry-alerts')->expectsOutput('Expiry at Madina: 1 batches expire within 90 days, 0 already expired. See Stock, Expiry.')->assertSuccessful();
    }

    private function expectsValidation(\Closure $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected it to be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($message, collect($e->errors())->flatten()->implode(' '));
        }
    }
}
