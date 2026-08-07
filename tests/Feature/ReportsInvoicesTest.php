<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportsInvoicesTest extends TestCase
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

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    private function makeOrder(string $status, int $total, ?Carbon $placedAt = null): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'channel' => 'web',
        ]);
        $order->status = $status;
        $order->placed_at = $placedAt ?? now('Africa/Accra');
        $order->save();

        return $order;
    }

    public function test_manager_can_view_the_invoices_and_sales_page(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.reports.invoices.index'))
            ->assertOk()
            ->assertSee('Invoices and sales')
            ->assertSee('Weekly invoices');
    }

    public function test_staff_cannot_view_the_invoices_and_sales_page(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.reports.invoices.index'))->assertForbidden();
    }

    public function test_current_week_summary_totals_revenue_orders_only(): void
    {
        $manager = $this->makeManager();
        $monday = now('Africa/Accra')->startOfWeek();

        $this->makeOrder('delivered', 5000, $monday->clone()->addDay());
        $this->makeOrder('delivered', 3000, $monday->clone()->addDays(2));
        $this->makeOrder('cancelled', 9000, $monday->clone()->addDays(3));

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.index'));

        $summary = $response->viewData('summary');
        $this->assertSame(8000, $summary['total']);
        $this->assertSame(2, $summary['orders_count']);
        $this->assertSame('Accra', $summary['city']);
    }

    public function test_last_week_link_shows_last_weeks_totals(): void
    {
        $manager = $this->makeManager();
        $lastMonday = now('Africa/Accra')->subWeek()->startOfWeek();

        $this->makeOrder('delivered', 7000, $lastMonday->clone()->addDay());

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.index', [
            'week' => $lastMonday->toDateString(),
        ]));

        $this->assertSame(7000, $response->viewData('summary')['total']);
        $this->assertTrue($response->viewData('isLastWeek'));
        $this->assertFalse($response->viewData('isThisWeek'));
    }

    public function test_weekly_history_groups_orders_by_iso_week(): void
    {
        $manager = $this->makeManager();
        $thisMonday = now('Africa/Accra')->startOfWeek();
        $lastMonday = $thisMonday->clone()->subWeek();

        $this->makeOrder('delivered', 5000, $thisMonday->clone()->addDay());
        $this->makeOrder('delivered', 2000, $lastMonday->clone()->addDay());
        $this->makeOrder('delivered', 1000, $lastMonday->clone()->addDays(2));

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.index'));
        $history = collect($response->viewData('history')->items());

        $thisWeekRow = $history->first(fn ($row) => $row['start']->isSameDay($thisMonday));
        $lastWeekRow = $history->first(fn ($row) => $row['start']->isSameDay($lastMonday));

        $this->assertSame(5000, $thisWeekRow['total']);
        $this->assertSame(3000, $lastWeekRow['total']);
        $this->assertSame(2, $lastWeekRow['orders_count']);
    }

    public function test_weekly_history_is_most_recent_first(): void
    {
        $manager = $this->makeManager();
        $thisMonday = now('Africa/Accra')->startOfWeek();

        $this->makeOrder('delivered', 1000, $thisMonday->clone()->subWeeks(2)->addDay());
        $this->makeOrder('delivered', 1000, $thisMonday->clone()->addDay());
        $this->makeOrder('delivered', 1000, $thisMonday->clone()->subWeeks(1)->addDay());

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.index'));
        $starts = collect($response->viewData('history')->items())->pluck('start')->map->toDateString()->values();

        $sorted = $starts->sort()->reverse()->values();
        $this->assertSame($sorted->all(), $starts->all());
    }

    public function test_csv_download_contains_the_week_summary(): void
    {
        $manager = $this->makeManager();
        $monday = now('Africa/Accra')->startOfWeek();
        $this->makeOrder('delivered', 5000, $monday->clone()->addDay());

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.download', [
            'format' => 'csv', 'week' => $monday->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $content = $response->streamedContent();

        $this->assertStringContainsString('Accra', $content);
        $this->assertStringContainsString('50.00', $content);
        $this->assertStringContainsString('GHS', $content);
    }

    public function test_xlsx_download_returns_a_spreadsheet_file(): void
    {
        $manager = $this->makeManager();
        $monday = now('Africa/Accra')->startOfWeek();
        $this->makeOrder('delivered', 5000, $monday->clone()->addDay());

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.download', [
            'format' => 'xlsx', 'week' => $monday->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertNotEmpty($response->getContent());
        // Every valid .xlsx is itself a zip archive — this is the zip
        // local-file-header magic number, a cheap real-format check.
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
    }

    public function test_pdf_download_returns_a_pdf_file(): void
    {
        $manager = $this->makeManager();
        $monday = now('Africa/Accra')->startOfWeek();
        $this->makeOrder('delivered', 5000, $monday->clone()->addDay());

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.download', [
            'format' => 'pdf', 'week' => $monday->toDateString(),
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_an_unknown_format_is_rejected(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.reports.invoices.download', ['format' => 'docx']))
            ->assertNotFound();
    }

    public function test_report_is_scoped_to_the_current_branch(): void
    {
        $manager = $this->makeManager();
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $monday = now('Africa/Accra')->startOfWeek();

        $this->makeOrder('delivered', 5000, $monday->clone()->addDay());

        $otherCustomer = Customer::create(['phone' => '+233209999999']);
        $otherOrder = Order::create([
            'reference' => 'ORD-'.uniqid(), 'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $otherCustomer->id, 'branch_id' => $otherBranch->id,
            'fulfilment_type' => 'pickup', 'subtotal' => 9000, 'total' => 9000,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'channel' => 'web',
        ]);
        $otherOrder->status = 'delivered';
        $otherOrder->placed_at = $monday->clone()->addDay();
        $otherOrder->save();

        $response = $this->actingAs($manager)->get(route('dashboard.reports.invoices.index'));

        $this->assertSame(5000, $response->viewData('summary')['total']);
    }
}
