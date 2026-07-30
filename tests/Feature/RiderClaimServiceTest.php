<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\RiderClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderClaimServiceTest extends TestCase
{
    use RefreshDatabase;

    private RiderClaimService $service;

    private Order $order;

    private User $riderA;

    private User $riderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RiderClaimService;

        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $customer = Customer::create(['phone' => '+233241111111']);

        $this->order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => str_repeat('a', 32),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        $this->order->status = 'ready';
        $this->order->save();

        $this->riderA = User::factory()->create(['name' => 'Kwame']);
        $this->riderB = User::factory()->create(['name' => 'Yaw']);
    }

    public function test_first_rider_to_claim_succeeds(): void
    {
        $result = $this->service->claim($this->order, $this->riderA);

        $this->assertTrue($result->claimed);
        $this->assertSame($this->riderA->id, $result->order->rider_id);
        $this->assertNotNull($result->order->claimed_at);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $this->order->id,
            'from_status' => 'ready',
            'to_status' => 'ready',
            'actor_type' => 'rider',
            'actor_id' => $this->riderA->id,
        ]);
    }

    public function test_second_rider_loses_the_race_and_learns_the_winners_name(): void
    {
        $this->service->claim($this->order, $this->riderA);

        $result = $this->service->claim($this->order, $this->riderB);

        $this->assertFalse($result->claimed);
        $this->assertSame('Kwame', $result->claimedByName);

        // Only one claim event was ever written — the loser wrote nothing.
        $this->assertDatabaseCount('order_events', 1);
    }

    public function test_claim_fails_when_order_is_not_ready(): void
    {
        $this->order->status = 'preparing';
        $this->order->save();

        $result = $this->service->claim($this->order, $this->riderA);

        $this->assertFalse($result->claimed);
        $this->assertNull($result->claimedByName);
    }
}
