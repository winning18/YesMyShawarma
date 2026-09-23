<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\DeliveryFeeCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderArrivalTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5560, 'lng' => -0.1969, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->menuItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 3500,
        ]);
        $this->branch->menuItems()->attach($this->menuItem->id, ['is_available' => true]);
    }

    private function assignRoleAt(User $user, string $role): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function makeRider(): User
    {
        return $this->assignRoleAt(User::factory()->create(), 'rider');
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
            'discount_total' => 0,
            'delivery_fee' => 0,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'channel' => 'web',
            'delivery_address_snapshot' => ['lat' => null, 'lng' => null, 'area_name' => 'Test Area'],
        ], $overrides));

        $order->status = $overrides['status'] ?? 'dispatched';
        $order->placed_at = now();
        $order->rider_id = $overrides['rider_id'] ?? null;
        $order->save();

        $order->payments()->create([
            'provider' => $order->payment_method, 'amount' => $order->total, 'currency' => 'GHS', 'status' => 'pending',
        ]);

        return $order;
    }

    public function test_rider_can_mark_a_dispatched_order_arrived_and_have_the_fee_calculated_from_their_own_position(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder(['rider_id' => $rider->id]);

        // Roughly 11km north of the branch.
        $response = $this->actingAs($rider)->postJson(route('orders.arrive', $order), [
            'lat' => 5.6560, 'lng' => -0.1969,
        ])->assertOk();

        $order->refresh();
        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->branch, 5.6560, -0.1969);

        $this->assertNotNull($order->arrived_at);
        $this->assertSame($expectedFee, $order->delivery_fee);
        $this->assertSame(3500 + $expectedFee, $order->total);
        $this->assertGreaterThan(DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $expectedFee);

        $response->assertJsonPath('data.arrived_at', fn ($value) => $value !== null);

        $event = $order->events()->where('meta->action', 'arrived')->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->meta['delivery_fee_calculated']);
        $this->assertSame($expectedFee, $event->meta['delivery_fee']);
    }

    public function test_fee_falls_back_to_the_minimum_when_the_rider_has_no_coordinates_either(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder(['rider_id' => $rider->id]);

        $this->actingAs($rider)->postJson(route('orders.arrive', $order), [])->assertOk();

        $order->refresh();
        $this->assertSame(DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $order->delivery_fee);
        $this->assertSame(3500 + DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $order->total);
    }

    public function test_arrival_never_overwrites_a_fee_already_priced_from_the_customers_own_location(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder([
            'rider_id' => $rider->id,
            'delivery_address_snapshot' => ['lat' => 5.5700, 'lng' => -0.1900, 'area_name' => 'Test Area'],
            'delivery_fee' => 1500,
            'total' => 3500 + 1500,
        ]);

        $this->actingAs($rider)->postJson(route('orders.arrive', $order), [
            'lat' => 5.9000, 'lng' => -0.1000,
        ])->assertOk();

        $order->refresh();
        $this->assertSame(1500, $order->delivery_fee);
        $this->assertNotNull($order->arrived_at);

        $event = $order->events()->where('meta->action', 'arrived')->first();
        $this->assertFalse($event->meta['delivery_fee_calculated']);
    }

    public function test_arrival_never_overwrites_a_fee_already_set_by_a_manual_staff_adjustment(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder([
            'rider_id' => $rider->id,
            'delivery_fee' => 2000,
            'total' => 3500 + 2000,
        ]);

        $this->actingAs($rider)->postJson(route('orders.arrive', $order), [
            'lat' => 5.6560, 'lng' => -0.1969,
        ])->assertOk();

        $order->refresh();
        $this->assertSame(2000, $order->delivery_fee);
    }

    public function test_a_different_rider_cannot_mark_the_order_arrived(): void
    {
        $rider = $this->makeRider();
        $otherRider = $this->makeRider();
        $order = $this->makeOrder(['rider_id' => $rider->id]);

        $this->actingAs($otherRider)->postJson(route('orders.arrive', $order), [])
            ->assertForbidden();

        $this->assertNull($order->fresh()->arrived_at);
    }

    public function test_staff_cannot_mark_an_order_arrived(): void
    {
        $rider = $this->makeRider();
        $staff = $this->assignRoleAt(User::factory()->create(), 'staff');
        $order = $this->makeOrder(['rider_id' => $rider->id]);

        $this->actingAs($staff)->postJson(route('orders.arrive', $order), [])
            ->assertForbidden();
    }

    public function test_cannot_mark_arrived_twice(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder(['rider_id' => $rider->id]);

        $this->actingAs($rider)->postJson(route('orders.arrive', $order), [])->assertOk();

        // OrderPolicy::arrive already excludes an order with arrived_at
        // set, same check OrderArrivalService itself would throw on — the
        // policy is what actually stops a second attempt here.
        $this->actingAs($rider)->postJson(route('orders.arrive', $order), [])->assertForbidden();
    }

    public function test_cannot_mark_arrived_before_dispatched(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder(['rider_id' => $rider->id, 'status' => 'ready']);

        $this->actingAs($rider)->postJson(route('orders.arrive', $order), [])
            ->assertForbidden();
    }

    public function test_cannot_mark_a_pickup_order_arrived(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder(['rider_id' => $rider->id, 'fulfilment_type' => 'pickup']);

        $this->actingAs($rider)->postJson(route('orders.arrive', $order), [])
            ->assertForbidden();
    }

    public function test_cash_to_collect_reflects_the_fee_after_arrival(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder(['rider_id' => $rider->id, 'payment_method' => 'cash']);

        $response = $this->actingAs($rider)->postJson(route('orders.arrive', $order), [
            'lat' => 5.6560, 'lng' => -0.1969,
        ])->assertOk();

        $order->refresh();
        $response->assertJsonPath('data.cash_to_collect', $order->total);
    }
}
