<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\MarketList;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\MarketLists\ExpenseKindMatcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfitReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $main;

    private Branch $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->main = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->other = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->main->id);
        $user->assignRole($role);

        return $user;
    }

    private function order(string $placedAt, int $subtotal, array $overrides = []): Order
    {
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)])->id,
            'branch_id' => ($overrides['branch'] ?? $this->main)->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => $subtotal,
            'discount_total' => $overrides['discount_total'] ?? 0,
            'delivery_fee' => $overrides['delivery_fee'] ?? 0,
            'total' => $subtotal,
            'payment_method' => $overrides['payment_method'] ?? 'cash',
            'payment_status' => 'paid',
            'channel' => 'web',
        ]);
        $order->status = $overrides['status'] ?? 'delivered';
        $order->placed_at = Carbon::parse($placedAt, 'Africa/Accra');
        $order->save();

        return $order;
    }

    /**
     * @param  list<array{0: string, 1: int, 2?: ?float, 3?: ?string}>  $items  description, amount, quantity, unit
     */
    private function purchase(string $date, array $items, string $status = MarketList::STATUS_APPROVED, ?Branch $branch = null): void
    {
        $list = MarketList::create([
            'branch_id' => ($branch ?? $this->main)->id, 'purchased_on' => $date, 'recorded_by' => User::factory()->create()->id,
            'recorder_role' => 'staff', 'status' => $status, 'total' => array_sum(array_column($items, 1)),
        ]);

        foreach ($items as $item) {
            $list->items()->create([
                'description' => $item[0], 'amount' => $item[1], 'quantity' => $item[2] ?? null, 'unit' => $item[3] ?? null,
                'expense_category_id' => app(ExpenseKindMatcher::class)->match($item[0])->id,
            ]);
        }
    }

    private function report(array $params): array
    {
        return $this->actingAs($this->userWithRole('owner'))
            ->get(route('dashboard.reports.profit.index', ['from' => '2026-10-05', 'to' => '2026-10-11', ...$params]))
            ->assertOk()
            ->viewData('report');
    }

    public function test_profit_is_sales_less_refunds_and_approved_purchases_per_day(): void
    {
        $order = $this->order('2026-10-05 12:00', 50000, ['discount_total' => 5000, 'delivery_fee' => 2000]);
        $this->order('2026-10-06 12:00', 30000);
        $this->order('2026-10-06 13:00', 99999, ['status' => 'cancelled']);
        $this->order('2026-10-06 14:00', 20000, ['payment_method' => 'bolt_food']);
        Refund::create([
            'order_id' => $order->id, 'branch_id' => $this->main->id, 'amount' => 1000, 'reason' => 'Late',
            'status' => 'completed', 'requested_by' => User::factory()->create()->id, 'completed_at' => Carbon::parse('2026-10-06 15:00', 'Africa/Accra'),
        ]);
        $this->purchase('2026-10-05', [['10kg chicken', 30000, 10, 'kg'], ['Trotro', 1000]]);
        $this->purchase('2026-10-06', [['Gas refill', 15000]], MarketList::STATUS_PENDING);

        $report = $this->report([]);
        $monday = $report['periods'][0];
        $tuesday = $report['periods'][1];

        $this->assertCount(7, $report['periods']);
        // 500 sales − 50 discount (delivery fee isn't sales) − 310 purchases.
        $this->assertSame(['sales' => 45000, 'refunds' => 0, 'purchases' => 31000, 'profit' => 14000], array_intersect_key($monday, array_flip(['sales', 'refunds', 'purchases', 'profit'])));
        // The pending gas refill is not counted; Bolt Food is its own line.
        $this->assertSame(['sales' => 30000, 'refunds' => 1000, 'purchases' => 0, 'profit' => 29000, 'bolt_food' => 20000, 'profit_with_bolt' => 49000], array_intersect_key($tuesday, array_flip(['sales', 'refunds', 'purchases', 'profit', 'bolt_food', 'profit_with_bolt'])));
        $this->assertSame(43000, $report['totals']['profit']);
        $this->assertSame(['count' => 1, 'total' => 15000], $report['awaiting']);
    }

    public function test_purchases_are_grouped_by_kind_with_quantities_when_units_match(): void
    {
        $this->purchase('2026-10-05', [['10kg chicken', 30000, 10, 'kg'], ['Trotro', 1000]]);
        $this->purchase('2026-10-07', [['Chicken wings', 12000, 4, 'KG'], ['Taxi', 2500], ['Onions', 3000, 1, 'bag']]);
        $this->purchase('2026-10-08', [['Tomatoes', 2000, 2, 'crate']]);

        $byKind = collect($this->report([])['by_kind'])->keyBy('kind');

        $this->assertSame(['Chicken', 'Vegetables', 'Transport'], $byKind->keys()->all());
        $this->assertSame(['kind' => 'Chicken', 'count' => 2, 'total' => 42000, 'quantity' => 14.0, 'unit' => 'kg'], $byKind['Chicken']);
        $this->assertSame(3500, $byKind['Transport']['total']);
        $this->assertNull($byKind['Transport']['quantity']);
        // bag + crate: no meaningful total quantity.
        $this->assertNull($byKind['Vegetables']['quantity']);
    }

    public function test_grouping_by_month_and_branch_filter(): void
    {
        $this->order('2026-09-20 12:00', 10000);
        $this->order('2026-10-02 12:00', 20000);
        $this->order('2026-10-03 12:00', 40000, ['branch' => $this->other]);
        $this->purchase('2026-10-02', [['Gas', 5000]]);

        $report = $this->report(['from' => '2026-09-01', 'to' => '2026-10-31', 'group_by' => 'month']);
        $this->assertSame(['September 2026', 'October 2026'], array_column($report['periods'], 'label'));
        $this->assertSame([10000, 60000], array_column($report['periods'], 'sales'));

        $mainOnly = $this->report(['from' => '2026-09-01', 'to' => '2026-10-31', 'group_by' => 'month', 'branch' => $this->main->id]);
        $this->assertSame([10000, 15000], array_column($mainOnly['periods'], 'profit'));
    }

    public function test_the_sales_statement_deducts_the_same_approved_purchases(): void
    {
        $this->order('2026-10-05 12:00', 50000);
        $this->purchase('2026-10-05', [['Chicken', 30000]]);
        $this->purchase('2026-10-06', [['Gas', 9000]], MarketList::STATUS_PENDING);

        $this->assertSame(20000, $this->report([])['totals']['profit']);

        $csv = $this->actingAs($this->userWithRole('owner'))->get(route('dashboard.reports.invoices.download', [
            'format' => 'csv', 'type' => 'sales', 'from' => '2026-10-05', 'to' => '2026-10-11',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Chicken', $csv);
        $this->assertStringNotContainsString('Gas', $csv);
    }

    public function test_downloads_and_access(): void
    {
        $this->order('2026-10-05 12:00', 50000);
        $this->purchase('2026-10-05', [['10kg chicken', 30000, 10, 'kg']]);
        $owner = $this->userWithRole('owner');

        $csv = $this->actingAs($owner)->get(route('dashboard.reports.profit.download', ['format' => 'csv', 'from' => '2026-10-05', 'to' => '2026-10-11']))
            ->assertOk()->getContent();
        $flat = collect(array_map('str_getcsv', explode("\n", trim($csv))))->map(fn ($row) => implode('|', array_filter($row, fn ($cell) => $cell !== '')))->all();
        $this->assertContains('TOTAL|500.00|0.00|300.00|200.00|0.00|200.00', $flat);
        $this->assertContains('Chicken|1|10 kg|300.00', $flat);

        $this->actingAs($owner)->get(route('dashboard.reports.profit.download', ['format' => 'pdf', 'from' => '2026-10-05', 'to' => '2026-10-11']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($owner)->get(route('dashboard.reports.profit.download', ['format' => 'xlsx', 'from' => '2026-10-05', 'to' => '2026-10-11']))->assertOk();

        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard.reports.profit.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->get(route('dashboard.reports.profit.index'))->assertOk()->assertSee('Purchases by kind');
    }
}
