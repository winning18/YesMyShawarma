<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\Orders\RiderAssignmentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RiderAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private RiderAssignmentService $service;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->service = app(RiderAssignmentService::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->customer = Customer::create(['phone' => '+233241111111']);
    }

    private function makeOrder(string $status = 'ready'): Order
    {
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        $order->status = $status;
        $order->save();

        return $order;
    }

    private function onShift(?string $name = null): User
    {
        $rider = User::factory()->create($name ? ['name' => $name] : []);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $rider->assignRole('rider');
        Shift::create(['user_id' => $rider->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);

        return $rider;
    }

    public function test_auto_assign_picks_the_only_eligible_rider(): void
    {
        $rider = $this->onShift();
        $order = $this->makeOrder();

        $assigned = $this->service->autoAssign($order);

        $this->assertSame($rider->id, $assigned?->id);
        $this->assertSame($rider->id, $order->fresh()->rider_id);
        $this->assertNotNull($order->fresh()->claimed_at);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'from_status' => 'ready',
            'to_status' => 'ready',
            'actor_type' => 'system',
            'actor_id' => null,
        ]);
    }

    public function test_auto_assign_returns_null_when_nobody_is_on_shift(): void
    {
        $order = $this->makeOrder();

        $assigned = $this->service->autoAssign($order);

        $this->assertNull($assigned);
        $this->assertNull($order->fresh()->rider_id);
    }

    public function test_auto_assign_never_picks_a_staff_member_even_when_they_are_the_only_one_on_shift(): void
    {
        // Regression: shifts carries no role column of its own — staff
        // and riders both start/end shifts through the exact same
        // mechanism (schema.md) — so an unfiltered "who's on shift" query
        // would silently auto-assign a staff member as the order's
        // "rider" the moment they're on shift and free. Same class of bug
        // already fixed once for RiderAvailabilityController's dropdown.
        $staff = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $staff->assignRole('staff');
        Shift::create(['user_id' => $staff->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);

        $order = $this->makeOrder();

        $assigned = $this->service->autoAssign($order);

        $this->assertNull($assigned);
        $this->assertNull($order->fresh()->rider_id);
    }

    public function test_auto_assign_picks_the_rider_over_a_staff_member_also_on_shift(): void
    {
        $staff = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $staff->assignRole('staff');
        Shift::create(['user_id' => $staff->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);

        $rider = $this->onShift();
        $order = $this->makeOrder();

        $assigned = $this->service->autoAssign($order);

        $this->assertSame($rider->id, $assigned?->id);
    }

    public function test_auto_assign_skips_a_rider_already_carrying_an_order(): void
    {
        $busyRider = $this->onShift('Busy Rider');
        $freeRider = $this->onShift('Free Rider');

        $existing = $this->makeOrder('dispatched');
        $existing->rider_id = $busyRider->id;
        $existing->claimed_at = now();
        $existing->save();

        $newOrder = $this->makeOrder();

        $assigned = $this->service->autoAssign($newOrder);

        $this->assertSame($freeRider->id, $assigned?->id);
    }

    public function test_auto_assign_returns_null_when_the_only_rider_is_busy(): void
    {
        $rider = $this->onShift();

        $existing = $this->makeOrder('ready');
        $existing->rider_id = $rider->id;
        $existing->claimed_at = now();
        $existing->save();

        $newOrder = $this->makeOrder();

        $assigned = $this->service->autoAssign($newOrder);

        $this->assertNull($assigned);
        $this->assertNull($newOrder->fresh()->rider_id);
    }

    public function test_auto_assign_prefers_the_least_recently_assigned_rider(): void
    {
        $recentlyAssigned = $this->onShift('Recently Assigned');
        $neverAssigned = $this->onShift('Never Assigned');

        // recentlyAssigned already delivered something a moment ago —
        // eligible again (order no longer ready/dispatched), but should
        // still lose to a rider who has never been assigned anything.
        $delivered = $this->makeOrder('delivered');
        $delivered->rider_id = $recentlyAssigned->id;
        $delivered->claimed_at = now();
        $delivered->save();

        $newOrder = $this->makeOrder();

        $assigned = $this->service->autoAssign($newOrder);

        $this->assertSame($neverAssigned->id, $assigned?->id);
    }

    public function test_auto_assign_round_robins_across_repeated_assignments(): void
    {
        $riderA = $this->onShift('Rider A');
        $riderB = $this->onShift('Rider B');

        $first = $this->makeOrder();
        $assignedFirst = $this->service->autoAssign($first);

        // Whichever rider got picked first (both had never been assigned,
        // so the tie-break is just collection order) should not be picked
        // again while the other has never carried anything.
        $other = $assignedFirst?->id === $riderA->id ? $riderB : $riderA;

        // Free up the first rider so both are eligible again for order two.
        $first->status = 'delivered';
        $first->save();

        $second = $this->makeOrder();
        $assignedSecond = $this->service->autoAssign($second);

        $this->assertSame($other->id, $assignedSecond?->id);
    }

    public function test_manual_assign_overrides_the_carrying_an_order_rule(): void
    {
        $rider = $this->onShift();
        $staff = User::factory()->create();

        $existing = $this->makeOrder('dispatched');
        $existing->rider_id = $rider->id;
        $existing->claimed_at = now();
        $existing->save();

        $newOrder = $this->makeOrder();

        $result = $this->service->assign($newOrder, $rider, $staff, 'staff', null);

        $this->assertSame($rider->id, $result->rider_id);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $newOrder->id,
            'actor_type' => 'staff',
            'actor_id' => $staff->id,
        ]);
    }
}
