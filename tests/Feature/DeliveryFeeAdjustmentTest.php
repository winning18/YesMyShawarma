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

class DeliveryFeeAdjustmentTest extends TestCase
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

    private function makeManager(): User
    {
        return $this->assignRoleAt(User::factory()->create(), 'manager');
    }

    private function makeStaff(): User
    {
        return $this->assignRoleAt(User::factory()->create(), 'staff');
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
            'delivery_fee' => DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS,
            'total' => 3500 + DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'channel' => 'web',
            'delivery_address_snapshot' => ['lat' => null, 'lng' => null, 'area_name' => 'Test Area'],
        ], $overrides));

        $order->status = $overrides['status'] ?? 'paid';
        $order->placed_at = now();
        $order->save();

        $order->payments()->create([
            'provider' => $order->payment_method, 'amount' => $order->total, 'currency' => 'GHS', 'status' => 'pending',
        ]);

        return $order;
    }

    public function test_manager_can_adjust_an_estimated_delivery_fee(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder();

        $this->actingAs($manager)->postJson(route('orders.adjust_delivery_fee', $order), [
            'delivery_fee' => '25.00', 'reason' => 'Landmark is much farther than the area estimate assumes.',
        ])->assertOk();

        $order->refresh();
        $this->assertSame(2500, $order->delivery_fee);
        $this->assertSame(3500 + 2500, $order->total);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'from_status' => 'paid', 'to_status' => 'paid',
        ]);

        $event = $order->events()->where('meta->action', 'delivery_fee_adjusted')->first();
        $this->assertNotNull($event);
        $this->assertSame(DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $event->meta['delivery_fee_old']);
        $this->assertSame(2500, $event->meta['delivery_fee_new']);
    }

    public function test_staff_cannot_adjust_delivery_fee(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makeOrder();

        $this->actingAs($staff)->postJson(route('orders.adjust_delivery_fee', $order), [
            'delivery_fee' => '25.00',
        ])->assertForbidden();
    }

    public function test_cannot_adjust_a_precisely_priced_delivery_fee(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder([
            'delivery_address_snapshot' => ['lat' => 5.5700, 'lng' => -0.1900, 'area_name' => 'Test Area'],
        ]);

        $this->actingAs($manager)->postJson(route('orders.adjust_delivery_fee', $order), [
            'delivery_fee' => '25.00',
        ])->assertStatus(422);

        $order->refresh();
        $this->assertSame(DeliveryFeeCalculator::MINIMUM_DELIVERY_FEE_PESEWAS, $order->delivery_fee);
    }

    public function test_cannot_adjust_delivery_fee_once_delivered(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder(['status' => 'delivered']);

        $this->actingAs($manager)->postJson(route('orders.adjust_delivery_fee', $order), [
            'delivery_fee' => '25.00',
        ])->assertForbidden();
    }

    public function test_cannot_adjust_delivery_fee_for_a_pickup_order(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder(['fulfilment_type' => 'pickup', 'delivery_fee' => 0, 'total' => 3500]);

        $this->actingAs($manager)->postJson(route('orders.adjust_delivery_fee', $order), [
            'delivery_fee' => '25.00',
        ])->assertForbidden();
    }
}
