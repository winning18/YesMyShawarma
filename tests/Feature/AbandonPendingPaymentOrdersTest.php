<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbandonPendingPaymentOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function makeOrderPlacedMinutesAgo(int $minutes): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'paystack',
            'payment_status' => 'pending',
        ]);
        $order->status = 'pending_payment';
        $order->placed_at = now()->subMinutes($minutes);
        $order->save();

        return $order;
    }

    public function test_abandons_orders_past_thirty_minutes(): void
    {
        $order = $this->makeOrderPlacedMinutesAgo(31);

        $this->artisan('orders:abandon-pending-payment')->assertSuccessful();

        $this->assertSame('abandoned', $order->fresh()->status);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'from_status' => 'pending_payment', 'to_status' => 'abandoned', 'actor_type' => 'system',
        ]);
    }

    public function test_does_not_abandon_orders_within_thirty_minutes(): void
    {
        $order = $this->makeOrderPlacedMinutesAgo(10);

        $this->artisan('orders:abandon-pending-payment')->assertSuccessful();

        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_does_not_touch_orders_already_paid(): void
    {
        $order = $this->makeOrderPlacedMinutesAgo(45);
        $order->status = 'paid';
        $order->save();

        $this->artisan('orders:abandon-pending-payment')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->status);
    }
}
