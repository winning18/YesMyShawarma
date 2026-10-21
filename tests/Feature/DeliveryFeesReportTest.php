<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Services\Reports\DeliveryFeeReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeliveryFeesReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-10-21 15:00:00'); // a Wednesday

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithRole(string $role, ?string $name = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function delivery(User $rider, int $fee, array $overrides = []): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);
        $subtotal = 5000;

        $order = Order::create(array_merge([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => $subtotal,
            'delivery_fee' => $fee,
            'total' => $subtotal + $fee,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ], $overrides));
        $order->status = $overrides['status'] ?? 'delivered';
        $order->rider_id = $rider->id;
        $order->delivered_at = $overrides['delivered_at'] ?? now()->subHour();
        $order->save();

        return $order;
    }

    private function fees(): DeliveryFeeReportService
    {
        return app(DeliveryFeeReportService::class);
    }

    public function test_only_delivered_delivery_orders_in_the_range_count(): void
    {
        $rider = $this->userWithRole('rider');
        $this->delivery($rider, 1500);
        $this->delivery($rider, 2000, ['status' => 'failed']);
        $this->delivery($rider, 3000, ['delivered_at' => now()->subWeeks(2)]);

        $summary = $this->fees()->summary($this->fees()->rows(now('Africa/Accra')->startOfWeek(), now('Africa/Accra')->endOfWeek(), ignoreBranchScope: true));

        $this->assertSame(['total' => 1500, 'cash' => 1500, 'prepaid' => 0, 'count' => 1], $summary);
    }

    public function test_fee_is_split_by_who_received_the_money(): void
    {
        $rider = $this->userWithRole('rider');

        $cash = $this->delivery($rider, 1000);

        $prepaid = $this->delivery($rider, 1200, ['payment_method' => 'paystack']);
        $prepaid->payments()->create(['provider' => 'paystack', 'amount' => $prepaid->total, 'currency' => 'GHS', 'status' => 'paid']);

        // Paystack charged before the fee existed; the fee was set on arrival and collected in cash.
        $feeOnArrival = $this->delivery($rider, 1400, ['payment_method' => 'paystack']);
        $feeOnArrival->payments()->create(['provider' => 'paystack', 'amount' => 5000, 'currency' => 'GHS', 'status' => 'paid']);

        $momo = $this->delivery($rider, 800, ['payment_method' => 'momo']);
        $momoFeeInCash = $this->delivery($rider, 900, ['payment_method' => 'momo', 'delivery_fee_paid_by' => 'rider_cash']);

        $splits = collect([$cash, $prepaid, $feeOnArrival, $momo, $momoFeeInCash])->map(fn (Order $o) => $this->fees()->split($o->fresh()));

        $this->assertSame(['fee' => 1000, 'cash' => 1000, 'prepaid' => 0], $splits[0]);
        $this->assertSame(['fee' => 1200, 'cash' => 0, 'prepaid' => 1200], $splits[1]);
        $this->assertSame(['fee' => 1400, 'cash' => 1400, 'prepaid' => 0], $splits[2]);
        $this->assertSame(['fee' => 800, 'cash' => 0, 'prepaid' => 800], $splits[3]);
        $this->assertSame(['fee' => 900, 'cash' => 900, 'prepaid' => 0], $splits[4]);
    }

    public function test_a_riders_app_totals_match_their_row_in_the_report(): void
    {
        $kofi = $this->userWithRole('rider', 'Kofi');
        $ama = $this->userWithRole('rider', 'Ama');
        $this->delivery($kofi, 1000);
        $this->delivery($kofi, 1500, ['delivered_at' => now('Africa/Accra')->startOfWeek()->addHour()]);
        $this->delivery($ama, 2000);

        $report = $this->actingAs($this->userWithRole('owner'))->get(route('dashboard.reports.delivery-fees.index'))->assertOk();
        $kofiRow = $report->viewData('riders')->firstWhere('rider', 'Kofi');

        $app = $this->actingAs($kofi, 'rider')->get(route('rider.history'))->assertOk();

        $this->assertSame(2500, $kofiRow['total']);
        $this->assertSame($kofiRow['total'], $app->viewData('thisWeek')['total']);
        $this->assertSame($kofiRow['count'], $app->viewData('thisWeek')['count']);
        $this->assertSame(4500, $report->viewData('summary')['total']);
        $app->assertSee('GH₵25.00');
    }

    public function test_fee_changes_are_flagged_from_order_events(): void
    {
        $rider = $this->userWithRole('rider');
        $order = $this->delivery($rider, 1300);
        OrderEvent::create([
            'order_id' => $order->id, 'from_status' => 'delivered', 'to_status' => 'delivered', 'actor_type' => 'user',
            'meta' => ['action' => 'delivery_fee_adjusted', 'delivery_fee_old' => 1000, 'delivery_fee_new' => 1300],
        ]);

        $row = $this->fees()->rows(now('Africa/Accra')->startOfDay(), now('Africa/Accra')->endOfDay(), ignoreBranchScope: true)->sole();

        $this->assertSame('Adjusted by manager', $row['fee_note']);
    }

    public function test_drilling_into_a_rider_lists_only_their_orders(): void
    {
        $kofi = $this->userWithRole('rider', 'Kofi');
        $ama = $this->userWithRole('rider', 'Ama');
        $kofisOrder = $this->delivery($kofi, 1000);
        $amasOrder = $this->delivery($ama, 2000);

        $this->actingAs($this->userWithRole('owner'))
            ->get(route('dashboard.reports.delivery-fees.index', ['rider' => $kofi->id]))
            ->assertOk()
            ->assertSee('Deliveries by Kofi')
            ->assertSee($kofisOrder->reference)
            ->assertDontSee($amasOrder->reference);
    }

    public function test_csv_lists_each_delivery_with_its_split(): void
    {
        $rider = $this->userWithRole('rider', 'Kofi');
        $order = $this->delivery($rider, 1500);

        $csv = $this->actingAs($this->userWithRole('owner'))
            ->get(route('dashboard.reports.delivery-fees.download'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('From customer in cash (GHS)', $csv);
        $this->assertStringContainsString($order->reference.',Osu,Kofi,,cash,15.00,15.00,0.00,', $csv);
    }

    public function test_financial_report_viewers_can_open_it_and_staff_cannot(): void
    {
        foreach (['manager', 'general_manager', 'owner'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('dashboard.reports.delivery-fees.index'))->assertOk();
        }

        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard.reports.delivery-fees.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard.reports.delivery-fees.download'))->assertForbidden();
    }

    public function test_rider_history_shows_each_fee_and_how_it_was_paid(): void
    {
        $rider = $this->userWithRole('rider');
        $this->delivery($rider, 1500);
        $failed = $this->delivery($rider, 900, ['status' => 'failed']);

        $this->actingAs($rider, 'rider')->get(route('rider.history'))
            ->assertOk()
            ->assertSee('GH₵15.00')
            ->assertSee('from customer')
            ->assertSee($failed->reference)
            ->assertDontSee('GH₵9.00');
    }

    public function test_staff_can_change_how_a_momo_delivery_fee_is_paid_until_delivered(): void
    {
        $rider = $this->userWithRole('rider');
        $staff = $this->userWithRole('staff');
        $order = $this->delivery($rider, 1500, ['payment_method' => 'momo', 'status' => 'dispatched']);

        $this->actingAs($staff)->post(route('orders.delivery_fee_paid_by', $order), ['delivery_fee_paid_by' => 'rider_cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rider_cash', $order->fresh()->delivery_fee_paid_by);
        $event = $order->events()->where('meta->action', 'delivery_fee_paid_by_changed')->sole();
        $this->assertSame(['action' => 'delivery_fee_paid_by_changed', 'from' => 'momo', 'to' => 'rider_cash'], $event->meta);

        $order->forceFill(['status' => 'delivered'])->save();
        $this->actingAs($staff)->post(route('orders.delivery_fee_paid_by', $order), ['delivery_fee_paid_by' => 'momo'])
            ->assertForbidden();
    }

    public function test_only_momo_delivery_orders_can_change_how_the_fee_is_paid(): void
    {
        $order = $this->delivery($this->userWithRole('rider'), 1500, ['status' => 'dispatched']);

        $this->actingAs($this->userWithRole('staff'))
            ->post(route('orders.delivery_fee_paid_by', $order), ['delivery_fee_paid_by' => 'rider_cash'])
            ->assertForbidden();
    }
}
