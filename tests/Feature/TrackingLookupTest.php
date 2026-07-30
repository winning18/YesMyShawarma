<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingLookupTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Customer $customer): Order
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

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
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_guest_can_find_order_by_phone_and_reference(): void
    {
        $customer = Customer::create(['phone' => '+233241111111']);
        $order = $this->makeOrder($customer);

        $response = $this->post(route('tracking.find'), [
            'phone' => '0241111111',
            'reference' => 'ORD-1',
        ]);

        $response->assertRedirect(route('tracking.show', $order));
    }

    public function test_guest_lookup_with_wrong_reference_fails(): void
    {
        $customer = Customer::create(['phone' => '+233241111111']);
        $this->makeOrder($customer);

        $this->post(route('tracking.find'), [
            'phone' => '0241111111',
            'reference' => 'DOES-NOT-EXIST',
        ])->assertSessionHasErrors('reference');
    }

    public function test_logged_in_customer_sees_their_order_history(): void
    {
        $customer = Customer::create(['phone' => '+233241111111', 'password' => 'secret123']);
        $this->makeOrder($customer);

        $response = $this->actingAs($customer, 'customer')->get(route('tracking.lookup'));

        $response->assertOk()->assertSee('ORD-1');
    }

    public function test_guest_visiting_lookup_sees_the_search_form_not_an_order_list(): void
    {
        $this->get(route('tracking.lookup'))->assertOk()->assertSee('Find my order');
    }
}
