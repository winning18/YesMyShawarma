<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeOrder(Branch $branch, string $status, int $total, array $timestamps = []): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => $timestamps['fulfilment_type'] ?? 'pickup',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => $timestamps['payment_method'] ?? 'cash',
            'payment_status' => 'paid',
            'channel' => $timestamps['channel'] ?? 'web',
        ]);
        $order->status = $status;
        $order->placed_at = $timestamps['placed_at'] ?? now('Africa/Accra');
        $order->accepted_at = $timestamps['accepted_at'] ?? null;
        $order->ready_at = $timestamps['ready_at'] ?? null;
        $order->dispatched_at = $timestamps['dispatched_at'] ?? null;
        $order->delivered_at = $timestamps['delivered_at'] ?? null;
        $order->save();

        return $order;
    }

    private function markPaidAt(Order $order, Carbon $when): void
    {
        $event = $order->events()->create([
            'from_status' => null, 'to_status' => 'paid', 'actor_type' => 'system', 'actor_id' => null,
        ]);
        DB::table('order_events')->where('id', $event->id)->update(['created_at' => $when]);
    }

    public function test_rider_cannot_view_reports(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        $this->actingAs($rider)->get(route('dashboard.reports.index'))->assertForbidden();
    }

    public function test_staff_sees_operational_but_not_financial_section(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.index'));

        $response->assertOk();
        $response->assertViewHas('canViewFinancial', false);
        $response->assertViewHas('financial', null);
        $response->assertDontSee('Discounts given');
    }

    public function test_manager_sees_the_financial_section(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));

        $response->assertOk();
        $response->assertViewHas('canViewFinancial', true);
        $response->assertSee('Discounts given');
    }

    public function test_default_range_is_the_last_seven_days(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));

        $from = $response->viewData('from');
        $to = $response->viewData('to');

        $this->assertSame(6, (int) floor($from->diffInDays($to)));
    }

    public function test_orders_by_day_and_status_breakdown(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $today = now('Africa/Accra');
        $this->makeOrder($this->branch, 'delivered', 5000, ['placed_at' => $today]);
        $this->makeOrder($this->branch, 'cancelled', 3000, ['placed_at' => $today]);
        $this->makeOrder($this->branch, 'rejected', 2000, ['placed_at' => $today->clone()->subDay()]);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index', [
            'from' => $today->clone()->subDay()->toDateString(),
            'to' => $today->toDateString(),
        ]));

        $operational = $response->viewData('operational');

        $this->assertSame(3, $operational['total_orders']);
        $this->assertSame(2, $operational['orders_by_day'][$today->toDateString()]);
        $this->assertSame(1, $operational['orders_by_day'][$today->clone()->subDay()->toDateString()]);
        $this->assertSame(1, $operational['status_breakdown']['delivered']);
        $this->assertSame(1, $operational['status_breakdown']['cancelled']);
        $this->assertSame(1, $operational['status_breakdown']['rejected']);
    }

    public function test_average_timings_are_computed_from_paid_event_and_denormalised_timestamps(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $paidAt = now('Africa/Accra');
        $acceptedAt = $paidAt->clone()->addMinutes(3);
        $readyAt = $acceptedAt->clone()->addMinutes(10);
        $dispatchedAt = $readyAt->clone()->addMinutes(1);
        $deliveredAt = $dispatchedAt->clone()->addMinutes(20);

        $order = $this->makeOrder($this->branch, 'delivered', 5000, [
            'placed_at' => $paidAt,
            'fulfilment_type' => 'delivery',
            'accepted_at' => $acceptedAt,
            'ready_at' => $readyAt,
            'dispatched_at' => $dispatchedAt,
            'delivered_at' => $deliveredAt,
        ]);
        $this->markPaidAt($order, $paidAt);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));
        $operational = $response->viewData('operational');

        $this->assertSame(3.0, $operational['avg_time_to_accept_minutes']);
        $this->assertSame(10.0, $operational['avg_prep_time_minutes']);
        $this->assertSame(20.0, $operational['avg_delivery_time_minutes']);
    }

    public function test_escalations_are_counted(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $order = $this->makeOrder($this->branch, 'paid', 4000);
        $order->events()->create([
            'from_status' => null, 'to_status' => 'paid', 'actor_type' => 'system', 'actor_id' => null,
            'meta' => ['escalation_level' => 5],
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));

        $this->assertSame(1, $response->viewData('operational')['escalations']);
    }

    public function test_financial_report_computes_revenue_discount_and_refund_totals(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->makeOrder($this->branch, 'delivered', 5000, ['payment_method' => 'cash']);
        $this->makeOrder($this->branch, 'delivered', 3000, ['payment_method' => 'paystack']);
        $this->makeOrder($this->branch, 'refunded', 2000);
        $this->makeOrder($this->branch, 'cancelled', 9000);

        $discounted = $this->makeOrder($this->branch, 'delivered', 4500, ['payment_method' => 'cash']);
        $promotion = Promotion::create(['code' => 'SAVE500', 'type' => 'fixed', 'value' => 500]);
        PromotionRedemption::create([
            'promotion_id' => $promotion->id, 'order_id' => $discounted->id,
            'customer_id' => $discounted->customer_id, 'amount_discounted' => 500,
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));
        $financial = $response->viewData('financial');

        // 5000 + 3000 + 4500 (refunded and cancelled excluded)
        $this->assertSame(12500, $financial['revenue_total']);
        $this->assertSame(500, $financial['discount_total']);
        $this->assertSame(2000, $financial['refund_total']);
        // 5000 (plain cash order) + 4500 (discounted cash order) — the
        // discount already reduced the order's total, so it isn't
        // subtracted again here.
        $this->assertSame(9500, $financial['revenue_by_payment_method']['cash']);
        $this->assertSame(3000, $financial['revenue_by_payment_method']['paystack']);
        $this->assertSame((int) round(12500 / 3), $financial['average_order_value']);
    }

    public function test_momo_appears_as_its_own_payment_method_row(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->makeOrder($this->branch, 'delivered', 5000, ['payment_method' => 'momo']);
        $this->makeOrder($this->branch, 'delivered', 3000, ['payment_method' => 'paystack']);
        $this->makeOrder($this->branch, 'delivered', 2000, ['payment_method' => 'cash']);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));
        $financial = $response->viewData('financial');

        $this->assertSame(5000, $financial['revenue_by_payment_method']['momo']);
        $this->assertSame(3000, $financial['revenue_by_payment_method']['paystack']);
        $this->assertSame(2000, $financial['revenue_by_payment_method']['cash']);
        $response->assertSee('momo');
    }

    public function test_report_respects_the_branch_switcher_unlike_the_owner_overview(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->makeOrder($this->branch, 'delivered', 5000);
        $this->makeOrder($this->otherBranch, 'delivered', 9000);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));

        $this->assertSame(1, $response->viewData('operational')['total_orders']);
        $this->assertSame(5000, $response->viewData('financial')['revenue_total']);
    }

    public function test_range_today_overrides_the_default_last_seven_days(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $today = now('Africa/Accra');
        $this->makeOrder($this->branch, 'delivered', 5000, ['placed_at' => $today]);
        $this->makeOrder($this->branch, 'delivered', 3000, ['placed_at' => $today->clone()->subDays(3)]);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index', ['range' => 'today']));

        $this->assertSame(1, $response->viewData('operational')['total_orders']);
    }

    public function test_range_last_month_computes_the_previous_calendar_month(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index', ['range' => 'last_month']));

        $from = $response->viewData('from');
        $to = $response->viewData('to');
        $expectedMonth = now('Africa/Accra')->subMonthNoOverflow()->month;

        $this->assertSame($expectedMonth, $from->month);
        $this->assertSame($expectedMonth, $to->month);
    }

    public function test_an_invalid_range_value_is_rejected(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.reports.index', ['range' => 'decade']))
            ->assertSessionHasErrors('range');
    }

    public function test_orders_by_channel_breaks_down_web_and_pos(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->makeOrder($this->branch, 'delivered', 5000, ['channel' => 'web']);
        $this->makeOrder($this->branch, 'delivered', 3000, ['channel' => 'web']);
        $this->makeOrder($this->branch, 'delivered', 4000, ['channel' => 'pos']);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));
        $operational = $response->viewData('operational');

        $this->assertSame(2, $operational['orders_by_channel']['web']);
        $this->assertSame(1, $operational['orders_by_channel']['pos']);
    }

    public function test_revenue_by_channel_breaks_down_web_and_pos(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->makeOrder($this->branch, 'delivered', 5000, ['channel' => 'web']);
        $this->makeOrder($this->branch, 'delivered', 4000, ['channel' => 'pos']);
        $this->makeOrder($this->branch, 'cancelled', 9000, ['channel' => 'pos']);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.index'));
        $financial = $response->viewData('financial');

        $this->assertSame(5000, $financial['revenue_by_channel']['web']);
        // Cancelled POS order excluded — non-revenue statuses apply the
        // same regardless of channel.
        $this->assertSame(4000, $financial['revenue_by_channel']['pos']);
    }
}
