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

class RiderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function makeRider(): User
    {
        $rider = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $rider->assignRole('rider');

        return $rider;
    }

    private function makeOrder(array $overrides = []): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create(array_merge([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ], $overrides));
        $order->status = $overrides['status'] ?? 'delivered';
        $order->save();

        return $order;
    }

    public function test_history_shows_delivered_and_failed_orders_assigned_to_this_rider(): void
    {
        $rider = $this->makeRider();
        $otherRider = $this->makeRider();

        $delivered = $this->makeOrder(['status' => 'delivered']);
        $delivered->rider_id = $rider->id;
        $delivered->save();

        $failed = $this->makeOrder(['status' => 'failed']);
        $failed->rider_id = $rider->id;
        $failed->save();

        $active = $this->makeOrder(['status' => 'dispatched']);
        $active->rider_id = $rider->id;
        $active->save();

        $someoneElses = $this->makeOrder(['status' => 'delivered']);
        $someoneElses->rider_id = $otherRider->id;
        $someoneElses->save();

        $response = $this->actingAs($rider)->get(route('rider.history'));

        $response->assertOk();
        $response->assertSee($delivered->reference);
        $response->assertSee($failed->reference);
        $response->assertDontSee($active->reference);
        $response->assertDontSee($someoneElses->reference);
    }

    public function test_history_page_loads_with_no_past_deliveries(): void
    {
        $rider = $this->makeRider();

        $this->actingAs($rider)->get(route('rider.history'))
            ->assertOk()
            ->assertSee("You haven't completed any deliveries yet.");
    }
}
