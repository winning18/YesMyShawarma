<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderDashboardTest extends TestCase
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

    private function makeOrder(Branch $branch, string $status = 'paid'): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => $status === 'pending_payment' ? 'pending' : 'paid',
        ]);
        $order->status = $status;
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_staff_sees_only_their_own_branchs_orders(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $ownOrder = $this->makeOrder($this->branchA);
        $otherBranchOrder = $this->makeOrder($this->branchB);

        $response = $this->actingAs($staff)->getJson(route('dashboard.orders.data'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($ownOrder->id));
        $this->assertFalse($ids->contains($otherBranchOrder->id));
    }

    public function test_staff_sees_customer_name_phone_and_delivery_location_but_not_coordinates(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama Owusu']);
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branchA->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'delivery_address_snapshot' => [
                'area_name' => 'Osu', 'landmark' => 'Near the blue gate',
                'ghanapost_code' => 'GA-123-4567', 'lat' => 5.55, 'lng' => -0.19,
            ],
        ]);
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();

        $response = $this->actingAs($staff)->getJson(route('dashboard.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertSame('Ama Owusu', $data['customer_name']);
        $this->assertSame('+233241111111', $data['customer_phone']);
        $this->assertSame('Osu', $data['delivery_address']['area_name']);
        $this->assertSame('Near the blue gate', $data['delivery_address']['landmark']);
        $this->assertNull($data['delivery_address']['lat']);
        $this->assertNull($data['delivery_address']['lng']);
    }

    public function test_manager_sees_customer_info_but_not_coordinates(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);

        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama Owusu']);
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branchA->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'delivery_address_snapshot' => ['area_name' => 'Osu', 'landmark' => 'Near the blue gate', 'lat' => 5.55, 'lng' => -0.19],
        ]);
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();

        $response = $this->actingAs($manager)->getJson(route('dashboard.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertSame('Ama Owusu', $data['customer_name']);
        $this->assertSame('+233241111111', $data['customer_phone']);
        $this->assertNull($data['delivery_address']['lat']);
        $this->assertNull($data['delivery_address']['lng']);
    }

    public function test_staff_can_accept_and_reject_orders(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        Shift::create(['user_id' => $staff->id, 'branch_id' => $this->branchA->id, 'started_at' => now()]);

        $order = $this->makeOrder($this->branchA);

        $this->actingAs($staff)
            ->postJson(route('orders.accept', $order))
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $rejectOrder = $this->makeOrder($this->branchA);

        $this->actingAs($staff)
            ->postJson(route('orders.reject', $rejectOrder))
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_staff_cannot_accept_an_order_without_an_active_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $order = $this->makeOrder($this->branchA);

        $this->actingAs($staff)
            ->postJson(route('orders.accept', $order))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Start your shift before accepting orders.');

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_staff_with_a_shift_at_a_different_branch_cannot_accept(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        Shift::create(['user_id' => $staff->id, 'branch_id' => $this->branchB->id, 'started_at' => now()]);

        $order = $this->makeOrder($this->branchA);

        $this->actingAs($staff)
            ->postJson(route('orders.accept', $order))
            ->assertUnprocessable();
    }

    public function test_staff_cannot_cancel_an_order(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $order = $this->makeOrder($this->branchA, 'accepted');

        $this->actingAs($staff)
            ->postJson(route('orders.cancel', $order), ['reason' => 'Out of stock'])
            ->assertForbidden();
    }

    public function test_manager_can_cancel_an_order_with_a_reason(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);

        $order = $this->makeOrder($this->branchA, 'accepted');

        $this->actingAs($manager)
            ->postJson(route('orders.cancel', $order), ['reason' => 'Kitchen closed early'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_without_a_reason_is_rejected(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branchA);

        $order = $this->makeOrder($this->branchA, 'accepted');

        $this->actingAs($manager)
            ->postJson(route('orders.cancel', $order), [])
            ->assertUnprocessable();
    }

    public function test_staff_from_another_branch_is_forbidden(): void
    {
        // Route model binding resolves before ResolveCurrentBranch has a
        // chance to populate the session on this (first) request, so
        // BranchScope hasn't filtered the order out of existence yet — the
        // policy's per-order-branch check is the actual backstop here,
        // hence 403 rather than a scope-driven 404.
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $otherBranchOrder = $this->makeOrder($this->branchB);

        $this->actingAs($staff)
            ->postJson(route('orders.accept', $otherBranchOrder))
            ->assertForbidden();
    }

    public function test_rider_cannot_accept_orders(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA);

        $this->actingAs($rider)
            ->postJson(route('orders.accept', $order))
            ->assertForbidden();
    }

    public function test_owner_can_act_on_orders_at_any_branch_without_selecting_one(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branchA);

        $orderAtOtherBranch = $this->makeOrder($this->branchB);

        $this->actingAs($owner)
            ->postJson(route('orders.accept', $orderAtOtherBranch))
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_advance_status_moves_an_accepted_order_through_the_pipeline(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $order = $this->makeOrder($this->branchA, 'accepted');

        $this->actingAs($staff)
            ->postJson(route('orders.advance', $order), ['to' => 'preparing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');
    }

    public function test_advance_status_rejects_an_invalid_target(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $order = $this->makeOrder($this->branchA, 'accepted');

        $this->actingAs($staff)
            ->postJson(route('orders.advance', $order), ['to' => 'not-a-real-status'])
            ->assertUnprocessable();
    }

    public function test_dashboard_data_includes_preparing_at_derived_from_order_events(): void
    {
        // No dedicated preparing_at column — derived from order_events
        // (see OrderResource), so this exercises the real transition path
        // (OrderStateMachine via the advance endpoint) rather than faking
        // the status directly, to prove the derivation actually works off
        // a real event row, not just a hand-inserted one.
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);
        Shift::create(['user_id' => $staff->id, 'branch_id' => $this->branchA->id, 'started_at' => now()]);

        $order = $this->makeOrder($this->branchA, 'accepted');

        $this->actingAs($staff)->postJson(route('orders.advance', $order), ['to' => 'preparing'])->assertOk();

        $response = $this->actingAs($staff)->getJson(route('dashboard.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertNotNull($data['preparing_at']);

        $expected = $order->events()->where('to_status', 'preparing')->first()->created_at;
        $this->assertSame($expected->toIso8601String(), $data['preparing_at']);
    }

    public function test_dashboard_data_omits_preparing_at_for_orders_never_prepared(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branchA);

        $order = $this->makeOrder($this->branchA, 'accepted');

        $response = $this->actingAs($staff)->getJson(route('dashboard.orders.data'));
        $data = collect($response->json('data'))->firstWhere('id', $order->id);

        $this->assertNull($data['preparing_at'] ?? null);
    }

    public function test_a_dispatched_delivery_can_be_marked_failed(): void
    {
        // Regression: 'failed' was missing from advance()'s allowed target
        // list even though OrderStateMachine allows dispatched -> failed —
        // there was no way to ever reach it, and refunded is only reachable
        // from failed/rejected/cancelled, so a failed delivery could never
        // be refunded either.
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branchA);

        $order = $this->makeOrder($this->branchA, 'dispatched');
        $order->rider_id = $rider->id;
        $order->save();

        $this->actingAs($rider)
            ->postJson(route('orders.advance', $order), ['to' => 'failed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');
    }
}
