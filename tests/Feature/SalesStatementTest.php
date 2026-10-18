<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\ShiftExpense;
use App\Models\User;
use App\Services\Reports\SalesStatementExporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SalesStatementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $main;

    private Branch $pokuase;

    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->main = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265',
            'address' => 'Adjacent Star Oil, Odumase', 'ghanapost_code' => 'GW-0286-6918',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '23:59',
        ]);
        $this->pokuase = Branch::create([
            'name' => 'Pokuase Y-Junction', 'slug' => 'pokuase-y-junction', 'phone' => '+233531907747',
            'address' => 'Footbridge, Nsawam Side, Nsawam Road',
            'lat' => 5.69, 'lng' => -0.29, 'opens_at' => '19:00', 'closes_at' => '23:59',
        ]);

        $this->monday = Carbon::parse('2026-10-05', 'Africa/Accra')->startOfDay();
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function owner(): User
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->main);

        return $owner;
    }

    private function manager(Branch $branch): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $branch);

        return $manager;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function order(Carbon $placedAt, array $overrides = []): Order
    {
        $subtotal = $overrides['subtotal'] ?? 5000;
        $discount = $overrides['discount_total'] ?? 0;
        $deliveryFee = $overrides['delivery_fee'] ?? 0;

        $order = Order::create([
            'reference' => $overrides['reference'] ?? 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)])->id,
            'branch_id' => ($overrides['branch'] ?? $this->main)->id,
            'fulfilment_type' => $deliveryFee > 0 ? 'delivery' : 'pickup',
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'delivery_fee' => $deliveryFee,
            'total' => $subtotal - $discount + $deliveryFee,
            'payment_method' => $overrides['payment_method'] ?? 'cash',
            'payment_status' => 'paid',
            'channel' => ($overrides['payment_method'] ?? 'cash') === 'bolt_food' ? 'pos' : 'web',
        ]);
        $order->status = $overrides['status'] ?? 'delivered';
        $order->placed_at = $placedAt;
        $order->save();

        return $order;
    }

    private function expense(Branch $branch, Carbon $at, int $amount, string $description = 'Gas refill'): void
    {
        $shift = Shift::create(['user_id' => User::factory()->create()->id, 'branch_id' => $branch->id, 'started_at' => $at]);
        $expense = ShiftExpense::create(['shift_id' => $shift->id, 'description' => $description, 'amount' => $amount]);
        $expense->created_at = $at;
        $expense->save();
    }

    private function refund(Order $order, int $amount, ?Carbon $completedAt, string $status = 'completed'): void
    {
        $manager = User::factory()->create();

        Refund::create([
            'order_id' => $order->id, 'branch_id' => $order->branch_id, 'amount' => $amount,
            'reason' => 'Complaint', 'status' => $status, 'requested_by' => $manager->id,
            'completed_by' => $completedAt ? $manager->id : null, 'completed_at' => $completedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function report(User $user, array $params = []): array
    {
        return $this->actingAs($user)->get(route('dashboard.reports.invoices.index', [
            'from' => '2026-10-05', 'to' => '2026-10-11', ...$params,
        ]))->assertOk()->viewData('report');
    }

    public function test_orders_are_grouped_by_day_with_a_total_per_day_and_numbered_through_the_period(): void
    {
        $this->order($this->monday->clone()->setTime(16, 22), ['subtotal' => 4900]);
        $this->order($this->monday->clone()->setTime(20, 0), ['subtotal' => 6800]);
        $this->order($this->monday->clone()->addDay()->setTime(17, 23), ['subtotal' => 5600]);

        $report = $this->report($this->owner());

        $this->assertCount(2, $report['days']);
        $this->assertSame('2026-10-05', $report['days'][0]['date']);
        $this->assertSame(11700, $report['days'][0]['totals']['sales']);
        $this->assertSame(5600, $report['days'][1]['totals']['sales']);
        $this->assertSame([1, 2], array_column($report['days'][0]['rows'], 'no'));
        $this->assertSame(3, $report['days'][1]['rows'][0]['no']);
        $this->assertSame('16:22', $report['days'][0]['rows'][0]['time']);
    }

    public function test_delivery_fee_is_listed_but_never_counted_as_sales(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000, 'delivery_fee' => 1500]);

        $report = $this->report($this->owner());
        $row = $report['days'][0]['rows'][0];

        $this->assertSame(5000, $row['sales']);
        $this->assertSame(1500, $row['delivery_fee']);
        $this->assertSame(6500, $row['total']);
        $this->assertSame(5000, $report['sales_total']);
        $this->assertSame(1500, $report['totals']['delivery_fee']);
        $this->assertSame(5000, $report['net_total']);
    }

    public function test_discount_reduces_sales_once_and_is_not_deducted_again(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 10000, 'discount_total' => 2000]);

        $report = $this->report($this->owner());

        $this->assertSame(8000, $report['sales_total']);
        $this->assertSame(2000, $report['totals']['discount']);
        $this->assertSame(8000, $report['net_total']);
    }

    public function test_net_sales_deducts_completed_refunds_and_expenses(): void
    {
        $order = $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 20000]);
        $this->order($this->monday->clone()->addDays(2)->setTime(19, 0), ['subtotal' => 10000]);
        $this->refund($order, 2500, $this->monday->clone()->addDays(3)->setTime(12, 0));
        $this->expense($this->main, $this->monday->clone()->addDay()->setTime(15, 0), 3000);
        $this->expense($this->main, $this->monday->clone()->addDays(4)->setTime(15, 0), 1200, 'Charcoal');

        $report = $this->report($this->owner());

        $this->assertSame(30000, $report['sales_total']);
        $this->assertSame(2500, $report['refunds_total']);
        $this->assertSame(4200, $report['expenses_total']);
        $this->assertSame(30000 - 2500 - 4200, $report['net_total']);
        $this->assertSame(['Gas refill', 'Charcoal'], array_column($report['expenses'], 'description'));
        $this->assertSame(
            ['Total sales (after discounts)', 'Refund deductions', 'Expenses', 'Net sales'],
            array_column($report['summary_lines'], 'label'),
        );
        $this->assertSame(-2500, $report['summary_lines'][1]['amount']);
    }

    public function test_only_refunds_completed_inside_the_period_are_deducted(): void
    {
        $earlierOrder = $this->order($this->monday->clone()->subWeek()->setTime(18, 0));
        $order = $this->order($this->monday->clone()->setTime(18, 0));

        // An order from last week refunded this week still comes off this week.
        $this->refund($earlierOrder, 1000, $this->monday->clone()->addDay()->setTime(10, 0));
        // Refunded after the period ends — next period's deduction.
        $this->refund($order, 700, $this->monday->clone()->addWeek()->setTime(10, 0));
        // Not yet completed — no money has left.
        $this->refund($order, 300, null, 'approved');

        $report = $this->report($this->owner());

        $this->assertSame(1000, $report['refunds_total']);
        $this->assertSame(1, $report['refunds_count']);
    }

    public function test_bolt_food_orders_are_kept_out_of_the_sales_report(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000]);
        $bolt = $this->order($this->monday->clone()->setTime(19, 0), ['subtotal' => 9000, 'payment_method' => 'bolt_food']);
        $this->refund($bolt, 1000, $this->monday->clone()->setTime(21, 0));

        $report = $this->report($this->owner());

        $this->assertSame(5000, $report['sales_total']);
        $this->assertSame(1, $report['orders_count']);
        $this->assertSame(0, $report['refunds_total']);
    }

    public function test_bolt_food_report_lists_only_bolt_food_orders_without_expenses(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000]);
        $this->order($this->monday->clone()->setTime(19, 0), ['subtotal' => 9000, 'payment_method' => 'bolt_food', 'reference' => 'YMGS-BOLT-1']);
        $this->order($this->monday->clone()->setTime(20, 0), ['subtotal' => 4000, 'payment_method' => 'bolt_food', 'status' => 'cancelled']);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 3000);

        $report = $this->report($this->owner(), ['type' => 'bolt_food']);

        $this->assertSame('Bolt Food report', $report['title']);
        $this->assertSame(9000, $report['sales_total']);
        $this->assertSame(9000, $report['net_total']);
        $this->assertSame('YMGS-BOLT-1', $report['days'][0]['rows'][0]['reference']);
        $this->assertSame([], $report['expenses']);
        $this->assertSame(1, $report['excluded_counts']['Cancelled']);
        $this->assertNotContains('delivery_fee', array_column($report['columns'], 'key'));
    }

    public function test_orders_not_counted_in_sales_are_counted_by_status(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000]);
        $this->order($this->monday->clone()->setTime(18, 5), ['status' => 'cancelled']);
        $this->order($this->monday->clone()->setTime(18, 6), ['status' => 'cancelled']);
        $this->order($this->monday->clone()->setTime(18, 7), ['status' => 'rejected']);
        $this->order($this->monday->clone()->setTime(18, 8), ['status' => 'failed', 'payment_method' => 'paystack']);
        $this->order($this->monday->clone()->setTime(18, 9), ['status' => 'abandoned', 'payment_method' => 'paystack']);
        $this->order($this->monday->clone()->setTime(18, 10), ['status' => 'pending_payment', 'payment_method' => 'paystack']);
        // Outside the period — not counted at all.
        $this->order($this->monday->clone()->subDay()->setTime(18, 0), ['status' => 'cancelled']);

        $report = $this->report($this->owner());

        $this->assertSame(
            ['Cancelled' => 2, 'Rejected' => 1, 'Abandoned' => 1, 'Refunded' => 0, 'Failed' => 1],
            $report['excluded_counts'],
        );
        $this->assertSame(5000, $report['sales_total']);
        $this->assertSame(1, $report['orders_count']);
    }

    public function test_single_branch_report_shows_that_branch_and_only_its_orders_and_expenses(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000]);
        $this->order($this->monday->clone()->setTime(19, 0), ['subtotal' => 7000, 'branch' => $this->pokuase]);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 1000);
        $this->expense($this->pokuase, $this->monday->clone()->setTime(15, 0), 2000);

        $report = $this->report($this->owner(), ['branch' => $this->pokuase->id]);

        $this->assertFalse($report['general']);
        $this->assertSame('Pokuase Y-Junction', $report['branch']->name);
        $this->assertSame(7000, $report['sales_total']);
        $this->assertSame(2000, $report['expenses_total']);
        $this->assertNotContains('branch', array_column($report['columns'], 'key'));
    }

    public function test_general_report_uses_the_ga_odumase_address_and_adds_a_branch_column(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000]);
        $this->order($this->monday->clone()->setTime(19, 0), ['subtotal' => 7000, 'branch' => $this->pokuase]);
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 1000);
        $this->expense($this->pokuase, $this->monday->clone()->setTime(15, 0), 2000);

        $report = $this->report($this->owner());

        $this->assertTrue($report['general']);
        $this->assertSame('ga-odumase', $report['branch']->slug);
        $this->assertContains('branch', array_column($report['columns'], 'key'));
        $this->assertSame(['Ga Odumase', 'Pokuase Y-Junction'], array_column($report['days'][0]['rows'], 'branch'));
        $this->assertSame(12000, $report['sales_total']);
        $this->assertSame(3000, $report['expenses_total']);
    }

    public function test_a_manager_only_ever_gets_their_own_branch(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000]);
        $this->order($this->monday->clone()->setTime(19, 0), ['subtotal' => 7000, 'branch' => $this->pokuase]);
        $this->expense($this->pokuase, $this->monday->clone()->setTime(15, 0), 2000);

        $report = $this->report($this->manager($this->main), ['branch' => $this->pokuase->id]);

        $this->assertSame('Ga Odumase', $report['branch']->name);
        $this->assertSame(5000, $report['sales_total']);
        $this->assertSame(0, $report['expenses_total']);
    }

    public function test_any_date_range_is_accepted_and_a_reversed_one_is_swapped(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 5000]);
        $this->order($this->monday->clone()->addDays(9)->setTime(18, 0), ['subtotal' => 3000]);

        $twoWeeks = $this->report($this->owner(), ['from' => '2026-10-05', 'to' => '2026-10-18']);
        $reversed = $this->report($this->owner(), ['from' => '2026-10-18', 'to' => '2026-10-05']);
        $oneDay = $this->report($this->owner(), ['from' => '2026-10-14', 'to' => '2026-10-14']);

        $this->assertSame(8000, $twoWeeks['sales_total']);
        $this->assertSame(8000, $reversed['sales_total']);
        $this->assertSame(3000, $oneDay['sales_total']);
        $this->assertSame('2026-10-05', $reversed['from']->toDateString());
    }

    public function test_an_unknown_report_type_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->get(route('dashboard.reports.invoices.index', ['type' => 'everything']))
            ->assertSessionHasErrors('type');
    }

    public function test_csv_matches_the_bolt_layout_with_day_totals_expenses_totals_and_counts(): void
    {
        $order = $this->order($this->monday->clone()->setTime(16, 22), ['subtotal' => 4900, 'delivery_fee' => 1000, 'reference' => 'YMGS-1']);
        $this->order($this->monday->clone()->addDay()->setTime(17, 23), ['subtotal' => 5600]);
        $this->order($this->monday->clone()->addDay()->setTime(17, 30), ['status' => 'cancelled']);
        $this->refund($order, 500, $this->monday->clone()->addDay()->setTime(10, 0));
        $this->expense($this->main, $this->monday->clone()->setTime(15, 0), 2000, 'Gas refill');

        $response = $this->actingAs($this->owner())->get(route('dashboard.reports.invoices.download', [
            'format' => 'csv', 'branch' => $this->main->id, 'from' => '2026-10-05', 'to' => '2026-10-11',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            '2026-10-05 -- 2026-10-11 '.config('app.name').' Sales Report - Ga Odumase.csv',
            $response->headers->get('content-disposition'),
        );

        $rows = array_map('str_getcsv', explode("\n", trim($response->getContent())));

        $this->assertSame(['No', 'Date', 'Time', 'Order ID', 'Type', 'Payment', 'Subtotal (ghs)', 'Discount (ghs)', 'Sales (ghs)', 'Delivery fee (rider) (ghs)', 'Customer paid (ghs)'], $rows[0]);
        $this->assertSame(['1', '2026-10-05', '16:22', 'YMGS-1', 'Delivery', 'Cash', '49.00', '-', '49.00', '10.00', '59.00'], $rows[1]);
        $this->assertSame(['', '2026-10-05', '', '', '', 'TOTAL', '49.00', '-', '49.00', '10.00', '59.00'], $rows[2]);

        $flat = collect($rows)->map(fn ($row) => implode('|', array_filter($row, fn ($cell) => $cell !== '')))->all();
        $this->assertContains('EXPENSES', $flat);
        $this->assertContains('2026-10-05|15:00|Gas refill|20.00', $flat);
        $this->assertContains('Total sales (after discounts)|105.00', $flat);
        $this->assertContains('Refund deductions|-5.00', $flat);
        $this->assertContains('Expenses|-20.00', $flat);
        $this->assertContains('Net sales|80.00', $flat);
        $this->assertContains('Cancelled|1', $flat);
        $this->assertContains('Failed|0', $flat);
    }

    public function test_xlsx_has_the_header_block_and_numeric_money_cells(): void
    {
        $this->order($this->monday->clone()->setTime(16, 22), ['subtotal' => 4900]);

        $response = $this->actingAs($this->owner())->get(route('dashboard.reports.invoices.download', [
            'format' => 'xlsx', 'from' => '2026-10-05', 'to' => '2026-10-11',
        ]));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        file_put_contents($path, $response->getContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('2026-10-05 - 2026-10-11', $sheet->getTitle());
        $this->assertSame('Sales report', $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('Adjacent Star Oil, Odumase · GW-0286-6918', $sheet->getCell('A4')->getValue());

        // Header block is rows 1–7, column headings row 8, first order row 9.
        $this->assertSame('Sales (ghs)', $sheet->getCell('J8')->getValue());
        $this->assertIsNumeric($sheet->getCell('J9')->getValue());
        $this->assertEquals(49, $sheet->getCell('J9')->getValue());
    }

    public function test_pdf_renders_with_logo_branch_address_and_totals(): void
    {
        $this->order($this->monday->clone()->setTime(16, 22), ['subtotal' => 4900]);
        $this->order($this->monday->clone()->setTime(18, 0), ['status' => 'rejected']);

        $response = $this->actingAs($this->owner())->get(route('dashboard.reports.invoices.download', [
            'format' => 'pdf', 'from' => '2026-10-05', 'to' => '2026-10-11',
        ]));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        // The PDF stream is compressed, so assert on the rendered view.
        $report = app(\App\Services\Reports\SalesStatementService::class)
            ->build('sales', $this->monday, $this->monday->clone()->endOfWeek(), ignoreBranchScope: true);
        $html = view('dashboard.reports.pdf.sales-statement', [
            'report' => $report,
            'address' => app(SalesStatementExporter::class)->address($report['branch']),
            'logoPath' => public_path('images/logo-web.png'),
            'fontDir' => resource_path('fonts/figtree'),
        ])->render();

        $this->assertFileExists(public_path('images/logo-web.png'));
        $this->assertStringContainsString(public_path('images/logo-web.png'), $html);
        $this->assertStringContainsString('Adjacent Star Oil, Odumase · GW-0286-6918', $html);
        $this->assertStringContainsString('Period of report: 2026-10-05 - 2026-10-11', $html);
        $this->assertStringContainsString('Net sales', $html);
        $this->assertStringContainsString('Rejected', $html);
        $this->assertStringContainsString('TOTAL', $html);
    }

    public function test_bolt_food_report_downloads_in_every_format(): void
    {
        $this->order($this->monday->clone()->setTime(19, 0), ['subtotal' => 9000, 'payment_method' => 'bolt_food']);

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $response = $this->actingAs($this->owner())->get(route('dashboard.reports.invoices.download', [
                'format' => $format, 'type' => 'bolt_food', 'from' => '2026-10-05', 'to' => '2026-10-11',
            ]));

            $response->assertOk();
            $this->assertStringContainsString('Bolt Food Report', $response->headers->get('content-disposition'));
        }

        $csv = $this->actingAs($this->owner())->get(route('dashboard.reports.invoices.download', [
            'format' => 'csv', 'type' => 'bolt_food', 'from' => '2026-10-05', 'to' => '2026-10-11',
        ]))->getContent();

        $this->assertStringContainsString('Total Bolt Food sales', $csv);
        $this->assertStringNotContainsString('EXPENSES', $csv);
    }

    public function test_weekly_history_row_matches_that_weeks_statement(): void
    {
        $this->order($this->monday->clone()->setTime(18, 0), ['subtotal' => 10000, 'discount_total' => 1000, 'delivery_fee' => 1500]);
        $this->order($this->monday->clone()->addDay()->setTime(18, 0), ['subtotal' => 5000]);
        $this->order($this->monday->clone()->addDay()->setTime(19, 0), ['status' => 'failed', 'payment_method' => 'paystack']);
        $this->order($this->monday->clone()->addDay()->setTime(20, 0), ['status' => 'pending_payment', 'payment_method' => 'paystack']);

        $response = $this->actingAs($this->owner())->get(route('dashboard.reports.invoices.index', [
            'from' => '2026-10-05', 'to' => '2026-10-11',
        ]));

        $week = collect($response->viewData('history')->items())
            ->first(fn (array $row) => $row['start']->toDateString() === '2026-10-05');

        $this->assertSame(14000, $week['total']);
        $this->assertSame(2, $week['orders_count']);
        $this->assertSame($response->viewData('report')['sales_total'], $week['total']);
    }

    public function test_staff_cannot_download_the_statement(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->main);

        $this->actingAs($staff)->get(route('dashboard.reports.invoices.download', ['format' => 'pdf']))->assertForbidden();
    }
}
