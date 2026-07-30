<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $customer = Customer::create(['phone' => '+233241111111']);

        $order = Order::create([
            'reference' => 'ORD-1',
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->status = 'preparing';
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_tracking_page_is_publicly_accessible(): void
    {
        $order = $this->makeOrder();

        $this->get(route('tracking.show', $order))->assertOk();
    }

    public function test_tracking_data_endpoint_returns_order_shape(): void
    {
        $order = $this->makeOrder();

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()
            ->assertJsonPath('data.reference', 'ORD-1')
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonPath('data.branch.name', 'Osu');
    }

    public function test_unknown_token_is_not_found(): void
    {
        $this->get('/track/does-not-exist')->assertNotFound();
    }
}
