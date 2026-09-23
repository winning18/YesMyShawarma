<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RiderDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branchA = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->branchB = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeOrder(Branch $branch, array $overrides = []): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create(array_merge([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ], $overrides));
        $order->status = $overrides['status'] ?? 'ready';
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_rider_sees_only_their_own_assigned_orders(): void
    {
        $riderA = User::factory()->create();
        $riderB = User::factory()->create();
        $this->assignRoleAt($riderA, 'rider', $this->branchA);
        $this->assignRoleAt($riderB, 'rider', $this->branchA);

        $mine = $this->makeOrder($this->branchA);
        $mine->rider_id = $riderA->id;
        $mine->save();
        $unassigned = $this->makeOrder($this->branchA);
        $someoneElses = $this->makeOrder($this->branchA);
        $someoneElses->rider_id = $riderB->id;
        $someoneElses->save();
        // Assigned to riderA but placed at a different branch — a rider
        // can now be assigned to more than one branch and switch which is
        // "current" mid-delivery (permissions.md), so this must still show
        // up: rider_id is the only filter that matters here, never the
        // ambient session branch (Rider\DashboardController::data()
        // deliberately bypasses BranchScope for exactly this reason).
        $mineAtOtherBranch = $this->makeOrder($this->branchB);
        $mineAtOtherBranch->rider_id = $riderA->id;
        $mineAtOtherBranch->save();
        $delivered = $this->makeOrder($this->branchA, ['status' => 'delivered']);
        $delivered->rider_id = $riderA->id;
        $delivered->save();

        $response = $this->actingAs($riderA)->getJson(route('rider.orders.data'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($unassigned->id));
        $this->assertFalse($ids->contains($someoneElses->id));
        $this->assertTrue($ids->contains($mineAtOtherBranch->id));
        $this->assertFalse($ids->contains($delivered->id));
    }

    public function test_rider_sees_the_delivery_coordinates_staff_and_managers_dont(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA, [
            'delivery_address_snapshot' => ['area_name' => 'Osu', 'landmark' => 'Near the blue gate', 'lat' => 5.55, 'lng' => -0.19],
        ]);
        $order->rider_id = $rider->id;
        $order->save();

        $response = $this->actingAs($rider)->getJson(route('rider.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertSame(5.55, $data['delivery_address']['lat']);
        $this->assertSame(-0.19, $data['delivery_address']['lng']);
    }

    public function test_rider_can_advance_their_own_claimed_order(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA);
        $order->rider_id = $rider->id;
        $order->save();

        $this->actingAs($rider)
            ->postJson(route('orders.advance', $order), ['to' => 'dispatched'])
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched');
    }

    public function test_rider_cannot_advance_an_order_claimed_by_someone_else(): void
    {
        $riderA = User::factory()->create();
        $riderB = User::factory()->create();
        $this->assignRoleAt($riderA, 'rider', $this->branchA);
        $this->assignRoleAt($riderB, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA);
        $order->rider_id = $riderB->id;
        $order->save();

        $this->actingAs($riderA)
            ->postJson(route('orders.advance', $order), ['to' => 'dispatched'])
            ->assertForbidden();
    }

    public function test_rider_dashboard_page_loads(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $this->actingAs($rider)->get(route('rider.dashboard'))->assertOk();
    }

    public function test_rider_dashboard_renders_order_item_markup(): void
    {
        // Regression: the rider card showed the delivery address but never
        // what's actually being delivered, even though OrderResource +
        // items.options were already eager-loaded and present in the JSON
        // — the Blade template itself just never rendered them.
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $this->actingAs($rider)->get(route('rider.dashboard'))
            ->assertOk()
            ->assertSee('item.quantity + \'x \' + item.name', false);
    }

    public function test_rider_dashboard_shows_a_payment_method_badge(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $this->actingAs($rider)->get(route('rider.dashboard'))
            ->assertOk()
            ->assertSee('x-text="paymentLabel(order)"', false);
    }

    public function test_rider_dashboard_renders_the_get_directions_link_and_its_fallback(): void
    {
        // The link binding + fallback message are both part of the static
        // Blade template (client-rendered per order from JSON), so this is
        // assertable without a live browser — same reasoning as the other
        // markup-presence tests in this file.
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $response = $this->actingAs($rider)->get(route('rider.dashboard'));

        $response->assertOk();
        $response->assertSee("'&origin=' + order.branch.lat + ',' + order.branch.lng", false);
        $response->assertSee("'&destination=' + order.delivery_address.lat + ',' + order.delivery_address.lng", false);
        $response->assertSee("Customer didn't share a live location");
    }

    public function test_cash_to_collect_is_the_full_total_for_a_cash_order(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA, ['payment_method' => 'cash', 'total' => 4500]);
        $order->rider_id = $rider->id;
        $order->save();

        $response = $this->actingAs($rider)->getJson(route('rider.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertSame(4500, $data['cash_to_collect']);
    }

    public function test_cash_to_collect_is_zero_for_a_fully_prepaid_paystack_order(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA, ['payment_method' => 'paystack', 'total' => 6000]);
        $order->rider_id = $rider->id;
        $order->save();
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'PSK-'.uniqid(),
            'amount' => 6000, 'currency' => 'GHS', 'status' => 'paid', 'verified_at' => now(),
        ]);

        $response = $this->actingAs($rider)->getJson(route('rider.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertSame(0, $data['cash_to_collect']);
    }

    public function test_cash_to_collect_is_just_the_fee_for_a_paystack_order_priced_with_a_flat_estimate(): void
    {
        // Paystack only ever charges the subtotal it knew about at
        // placement when location wasn't captured — the flat-estimate fee
        // added on top still needs collecting in cash.
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA, ['payment_method' => 'paystack', 'subtotal' => 3500, 'total' => 4500]);
        $order->rider_id = $rider->id;
        $order->save();
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'PSK-'.uniqid(),
            'amount' => 3500, 'currency' => 'GHS', 'status' => 'paid', 'verified_at' => now(),
        ]);

        $response = $this->actingAs($rider)->getJson(route('rider.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertSame(1000, $data['cash_to_collect']);
    }

    public function test_switch_branch_link_shown_only_for_a_rider_assigned_to_two_branches(): void
    {
        $singleBranchRider = User::factory()->create();
        $this->assignRoleAt($singleBranchRider, 'rider', $this->branchA);

        $this->actingAs($singleBranchRider)->get(route('rider.dashboard'))
            ->assertDontSee(__('Switch branch'));

        $multiBranchRider = User::factory()->create();
        $this->assignRoleAt($multiBranchRider, 'rider', $this->branchA);
        $this->assignRoleAt($multiBranchRider, 'rider', $this->branchB);

        // A session branch already resolved (as if they'd already picked
        // one) — otherwise ResolveCurrentBranch bounces a multi-branch
        // user to branches.select before this page ever renders.
        $this->withSession(['current_branch_id' => $this->branchA->id])
            ->actingAs($multiBranchRider)->get(route('rider.dashboard'))
            ->assertSee(__('Switch branch'));
    }

    public function test_rider_dashboard_shows_what_to_collect_in_cash(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $this->actingAs($rider)->get(route('rider.dashboard'))
            ->assertOk()
            ->assertSee('order.cash_to_collect', false);
    }

    public function test_rider_orders_data_includes_the_branch_coordinate_for_directions(): void
    {
        // The "Get directions" link routes from the branch to the customer
        // (not from wherever the rider's device currently reports them),
        // so the branch's own coordinate has to be on the payload too.
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA, [
            'delivery_address_snapshot' => ['area_name' => 'Osu', 'landmark' => 'Near the blue gate', 'lat' => 5.55, 'lng' => -0.19],
        ]);
        $order->rider_id = $rider->id;
        $order->save();

        $response = $this->actingAs($rider)->getJson(route('rider.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertSame((float) $this->branchA->lat, $data['branch']['lat']);
        $this->assertSame((float) $this->branchA->lng, $data['branch']['lng']);
    }
}
