<?php

namespace Tests\Feature;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private OrderStateMachine $machine;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machine = app(OrderStateMachine::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->customer = Customer::create(['phone' => '+233241111111']);
    }

    private function makeOrder(string $status = 'pending_payment'): Order
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

    public function test_valid_transition_stamps_timestamp_and_writes_event(): void
    {
        $order = $this->makeOrder('preparing');

        $result = $this->machine->transition($order, 'ready', 'staff', actorId: 1);

        $this->assertSame('ready', $result->status);
        $this->assertNotNull($result->ready_at);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'from_status' => 'preparing',
            'to_status' => 'ready',
            'actor_type' => 'staff',
            'actor_id' => 1,
        ]);
    }

    public function test_delivered_transition_computes_and_stores_the_distance_based_delivery_fee(): void
    {
        $order = $this->makeOrder('dispatched');
        $order->update([
            'delivery_address_snapshot' => [
                'area_id' => null, 'area_name' => null,
                'ghanapost_code' => 'GA-123-4567', 'landmark' => 'Near the mall',
                'lat' => 5.51, 'lng' => -0.11,
            ],
        ]);
        $order->payments()->create(['provider' => 'cash', 'amount' => $order->total, 'currency' => 'GHS', 'status' => 'pending']);

        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($order->branch, 5.51, -0.11);

        $result = $this->machine->transition($order, 'delivered', 'rider', actorId: 1);

        $this->assertSame($expectedFee, $result->delivery_fee);
        $this->assertSame(3500 + $expectedFee, $result->total);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'cash', 'amount' => $result->total,
        ]);
    }

    public function test_delivered_transition_reconciles_a_momo_payment_row_too(): void
    {
        // Momo behaves exactly like cash here — Order::MANUALLY_SETTLED_
        // PAYMENT_METHODS, both settled in person, neither known to have
        // its final fee until the trip's actually made.
        $order = $this->makeOrder('dispatched');
        $order->update([
            'payment_method' => 'momo',
            'delivery_address_snapshot' => [
                'area_id' => null, 'area_name' => null,
                'ghanapost_code' => 'GA-123-4567', 'landmark' => 'Near the mall',
                'lat' => 5.51, 'lng' => -0.11,
            ],
        ]);
        $order->payments()->create(['provider' => 'momo', 'amount' => $order->total, 'currency' => 'GHS', 'status' => 'pending']);

        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($order->branch, 5.51, -0.11);

        $result = $this->machine->transition($order, 'delivered', 'rider', actorId: 1);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'momo', 'amount' => $result->total,
        ]);
    }

    public function test_pickup_order_reaching_delivered_leaves_fee_at_zero(): void
    {
        $order = $this->makeOrder('dispatched');
        $order->update(['fulfilment_type' => 'pickup']);

        $result = $this->machine->transition($order, 'delivered', 'rider', actorId: 1);

        $this->assertSame(0, $result->delivery_fee);
        $this->assertSame(3500, $result->total);
    }

    public function test_illegal_transition_throws(): void
    {
        $order = $this->makeOrder('pending_payment');

        $this->expectException(InvalidOrderTransitionException::class);

        $this->machine->transition($order, 'delivered', 'system');
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $order = $this->makeOrder('accepted');

        $this->expectException(InvalidOrderTransitionException::class);

        $this->machine->transition($order, 'cancelled', 'manager', actorId: 1);
    }

    public function test_cancellation_with_reason_succeeds(): void
    {
        $order = $this->makeOrder('accepted');

        $result = $this->machine->transition(
            $order, 'cancelled', 'manager', actorId: 1, cancellationReason: 'Customer requested cancellation'
        );

        $this->assertSame('cancelled', $result->status);
        $this->assertSame('Customer requested cancellation', $result->cancellation_reason);
        $this->assertNotNull($result->cancelled_at);
    }

    public function test_non_system_actor_requires_actor_id(): void
    {
        $order = $this->makeOrder('paid');

        $this->expectException(InvalidOrderTransitionException::class);

        $this->machine->transition($order, 'accepted', 'staff');
    }

    public function test_system_actor_does_not_require_actor_id(): void
    {
        $order = $this->makeOrder('pending_payment');

        $result = $this->machine->transition($order, 'paid', 'system');

        $this->assertSame('paid', $result->status);
    }

    public function test_refund_reachable_from_failed_rejected_and_cancelled(): void
    {
        foreach (['failed', 'rejected', 'cancelled'] as $from) {
            $order = $this->makeOrder($from);

            $result = $this->machine->transition($order, 'refunded', 'owner', actorId: 1);

            $this->assertSame('refunded', $result->status);
        }
    }

    public function test_refund_not_reachable_from_delivered(): void
    {
        $order = $this->makeOrder('delivered');

        $this->expectException(InvalidOrderTransitionException::class);

        $this->machine->transition($order, 'refunded', 'owner', actorId: 1);
    }

    public function test_ready_transition_auto_assigns_an_on_shift_rider_for_delivery_orders(): void
    {
        $rider = User::factory()->create();
        Shift::create(['user_id' => $rider->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);

        $order = $this->makeOrder('preparing');

        $result = $this->machine->transition($order, 'ready', 'staff', actorId: 1);

        $this->assertSame($rider->id, $result->rider_id);
    }

    public function test_ready_transition_does_not_assign_a_rider_to_a_pickup_order(): void
    {
        $rider = User::factory()->create();
        Shift::create(['user_id' => $rider->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);

        $order = $this->makeOrder('preparing');
        $order->update(['fulfilment_type' => 'pickup']);

        $result = $this->machine->transition($order, 'ready', 'staff', actorId: 1);

        $this->assertNull($result->rider_id);
    }
}
