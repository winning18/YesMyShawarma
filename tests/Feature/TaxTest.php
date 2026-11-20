<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Refund;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Printing\EscPosRenderer;
use App\Services\Printing\ReceiptService;
use App\Services\Reports\OrderReportService;
use App\Services\Reports\SalesStatementService;
use App\Services\Reports\TaxReportService;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\SettingsService;
use App\Services\Tax\TaxService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 9: optional tax — inclusive or added on top, the
 * delivery fee taxed or not, kept on each order as it was at the sale.
 */
class TaxTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $item;

    private DeliveryArea $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5560, 'lng' => -0.1969, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->area = DeliveryArea::create(['name' => 'Osu']);
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->item = MenuItem::create(['category_id' => $category->id, 'name' => 'Wrap', 'slug' => 'wrap', 'base_price' => 5000]);
        $this->branch->menuItems()->attach($this->item->id, ['is_available' => true]);
    }

    private function ghana(bool $inclusive): void
    {
        app(TaxService::class)->save($inclusive, TaxService::GHANA_PRESET);
    }

    private function order(string $paymentMethod = 'cash', int $quantity = 2, string $fulfilment = 'pickup'): Order
    {
        return app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: $fulfilment,
            paymentMethod: $paymentMethod,
            items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: $quantity, optionQuantities: [])],
            deliveryAddress: $fulfilment === 'delivery' ? new DeliveryAddressData(areaId: $this->area->id, ghanapostCode: null, landmark: 'Mall', lat: null, lng: null) : null,
        ));
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole('owner');

        return $user;
    }

    public function test_no_taxes_set_up_charges_nothing(): void
    {
        $order = $this->order();

        $this->assertSame(10000, $order->total);
        $this->assertSame(0, (int) $order->fresh()->tax_total);
        $this->assertSame(0, $order->taxes()->count());
    }

    public function test_tax_added_on_top_goes_on_the_total(): void
    {
        $this->ghana(inclusive: false);

        $order = $this->order()->fresh();

        $this->assertSame(12000, (int) $order->total);
        $this->assertSame(2000, (int) $order->tax_total);
        $this->assertSame([['VAT', 1500], ['NHIL', 250], ['GETFund', 250]], $order->taxes()->orderBy('id')->get()->map(fn ($t) => [$t->name, $t->food_amount])->all());
        $this->assertSame(12000, (int) $order->payments()->first()->amount, 'the cash taken includes the tax');
    }

    public function test_tax_inside_the_prices_leaves_the_total_alone(): void
    {
        $this->ghana(inclusive: true);

        $order = $this->order(quantity: 3)->fresh(); // GH₵150.00, tax inside at 20%

        $this->assertSame(15000, (int) $order->total);
        $this->assertSame(2500, (int) $order->tax_total); // 15000 × 20 / 120
        $this->assertSame([1875, 313, 312], $order->taxes()->orderBy('id')->pluck('food_amount')->all());
        $this->assertTrue((bool) $order->taxes()->first()->inclusive);
    }

    public function test_bolt_food_freebies_and_a_switched_off_module_carry_no_tax(): void
    {
        $this->ghana(inclusive: false);

        $this->assertSame(10000, (int) $this->order('bolt_food')->total);

        app(ModuleService::class)->set(ModuleService::TAX, false);
        $this->assertSame(10000, (int) app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111112', customerName: 'Kofi', branchId: $this->branch->id, fulfilmentType: 'pickup', paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 2, optionQuantities: [])],
        ))->total);
        $this->assertFalse(app(TaxService::class)->charging());
    }

    public function test_a_rate_change_never_touches_an_order_already_placed(): void
    {
        $this->ghana(inclusive: false);
        $order = $this->order();

        app(TaxService::class)->save(false, [['name' => 'VAT', 'rate_basis_points' => 2500]]);

        $this->assertSame(12000, (int) $order->fresh()->total);
        $this->assertSame(3, $order->taxes()->count());
    }

    public function test_the_delivery_fee_is_never_taxed(): void
    {
        $this->ghana(inclusive: false);
        $order = $this->order(fulfilment: 'delivery')->fresh(); // no location: fee priced later

        $this->assertSame(12000, (int) $order->total);

        $order->delivery_fee = 1000;
        app(TaxService::class)->retotal($order);
        $order->save();

        // Food 10000 + 20% tax on the food + the fee untaxed.
        $this->assertSame(2000, (int) $order->fresh()->tax_total);
        $this->assertSame(13000, (int) $order->fresh()->total);
        $this->assertSame([0, 0, 0], $order->taxes()->orderBy('id')->pluck('delivery_amount')->all());
    }

    public function test_the_sales_report_takes_a_refunds_tax_off_the_tax_not_the_sales(): void
    {
        $this->ghana(inclusive: false);
        $order = $this->order(); // 12000: 10000 sales + 2000 tax

        Refund::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'amount' => 6000, 'reason' => 'Wrong item',
            'status' => Refund::STATUS_COMPLETED, 'requested_by' => $owner = $this->owner()->id, 'completed_by' => $owner, 'completed_at' => now(),
        ]);

        $report = app(SalesStatementService::class)->build('sales', Carbon::now('Africa/Accra')->startOfDay(), Carbon::now('Africa/Accra')->endOfDay(), ignoreBranchScope: true);

        $this->assertSame(10000, $report['sales_total']);
        $this->assertSame(5000, $report['refunds_total']);
        $this->assertSame(5000, $report['net_total']);
        $this->assertContains(['label' => 'Tax collected (not sales)', 'value' => 'GHS 10.00'], $report['memo_lines']);
    }

    public function test_reports_count_sales_before_tax_and_tills_expect_the_tax(): void
    {
        $this->ghana(inclusive: true);
        $this->order(quantity: 3); // 15000 incl. 2500 tax

        $summary = app(OrderReportService::class)->financialSummary(now()->subHour(), now()->addHour(), ignoreBranchScope: true);

        $this->assertSame(12500, $summary['revenue_total']);
        $this->assertSame(15000, $summary['till_total']);
        $this->assertSame(2500, $summary['tax_total']);
    }

    public function test_the_tax_report_takes_off_tax_on_refunds(): void
    {
        $this->ghana(inclusive: false);
        $order = $this->order(); // 12000, 2000 tax

        Refund::forceCreate([
            'order_id' => $order->id, 'branch_id' => $this->branch->id, 'amount' => 6000, 'reason' => 'Wrong item',
            'status' => Refund::STATUS_COMPLETED, 'requested_by' => $owner = $this->owner()->id, 'completed_by' => $owner, 'completed_at' => now(),
        ]);

        $report = app(TaxReportService::class)->build(Carbon::now('Africa/Accra')->startOfDay(), Carbon::now('Africa/Accra')->endOfDay(), ignoreBranchScope: true);

        $this->assertSame(10000, $report['taxable']);
        $this->assertSame(2000, $report['tax_total']);
        $this->assertSame(1000, $report['refunded_tax']);
        $this->assertSame(1000, $report['due']);
        $this->assertSame(['VAT 15%', 'GETFund 2.5%', 'NHIL 2.5%'], collect($report['lines'])->pluck('label')->all());
    }

    public function test_receipts_show_the_tax_lines_and_the_tin(): void
    {
        app(SettingsService::class)->set(BusinessProfile::TIN, 'C0012345678');
        $this->ghana(inclusive: false);

        $data = app(ReceiptService::class)->receipt($this->order());

        $this->assertSame('C0012345678', $data['tin']);
        $this->assertSame([['label' => 'VAT 15%', 'amount' => 1500], ['label' => 'NHIL 2.5%', 'amount' => 250], ['label' => 'GETFund 2.5%', 'amount' => 250]], $data['taxes']);

        $bytes = app(EscPosRenderer::class)->receipt($data, new Printer(['paper_width' => 80]));
        $this->assertStringContainsString('TIN C0012345678', $bytes);
        $this->assertStringContainsString('VAT 15%', $bytes);
    }

    public function test_the_owner_sets_up_taxes_on_the_tax_page(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard.tax.edit'))->assertOk()->assertSee('Use Ghana');

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])
            ->put(route('dashboard.tax.update'), [
                'inclusive' => '0',
                'lines' => [['name' => 'VAT', 'rate' => '15'], ['name' => '', 'rate' => ''], ['name' => 'NHIL', 'rate' => '2.5']],
            ])->assertSessionHasNoErrors();

        $this->assertSame(['inclusive' => false, 'lines' => [
            ['name' => 'VAT', 'rate_basis_points' => 1500], ['name' => 'NHIL', 'rate_basis_points' => 250],
        ]], app(TaxService::class)->config());

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])
            ->put(route('dashboard.tax.update'), ['inclusive' => '1', 'lines' => [['name' => 'VAT', 'rate' => '150']]])
            ->assertSessionHasErrors('lines.0.rate');
    }

    public function test_the_tax_pages_disappear_with_the_module_and_managers_cant_change_taxes(): void
    {
        $manager = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $manager->assignRole('manager');
        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard.tax.edit'))->assertForbidden();

        app(ModuleService::class)->set(ModuleService::TAX, false);
        $owner = $this->owner();
        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])->get(route('dashboard.tax.edit'))->assertNotFound();
        $this->actingAs($owner)->withSession(['current_branch_id' => $this->branch->id])->get(route('dashboard.reports.tax.index'))->assertNotFound();
    }

    public function test_split_matches_for_odd_amounts(): void
    {
        $tax = app(TaxService::class);

        $this->assertSame([150, 25, 25], $tax->split(1001, [1500, 250, 250], false));
        $this->assertSame([125, 21, 21], $tax->split(1001, [1500, 250, 250], true)); // 1001 × 20/120 = 167
        $this->assertSame([0, 0], $tax->split(0, [1500, 250], true));
        $this->assertSame('NHIL 2.5%', TaxService::label('NHIL', 250));
    }
}
