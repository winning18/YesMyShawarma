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
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderTransferTest extends TestCase
{
    use RefreshDatabase;

    private Branch $origin;

    private Branch $closer;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Osu is 5km-ish from the delivery point used below, Airport
        // Residential is much closer — the exact numbers don't matter, only
        // that closer's calculated fee is meaningfully lower than origin's.
        $this->origin = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5560, 'lng' => -0.1969, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->closer = Branch::create([
            'name' => 'Airport Residential', 'slug' => 'airport', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6037, 'lng' => -0.1870, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->menuItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 3500,
        ]);

        foreach ([$this->origin, $this->closer] as $branch) {
            $branch->menuItems()->attach($this->menuItem->id, ['is_available' => true]);
        }
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->origin);

        return $manager;
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->origin);

        return $staff;
    }

    private function makeOrder(array $overrides = []): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create(array_merge([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->origin->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'discount_total' => 0,
            'delivery_fee' => 2500,
            'total' => 6000,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'channel' => 'web',
            // Somewhere between the two branches, but close enough to
            // 'closer' that it prices lower from there than from 'origin'.
            'delivery_address_snapshot' => ['lat' => 5.5900, 'lng' => -0.1900, 'area_name' => 'Test Area'],
        ], $overrides));

        $order->status = $overrides['status'] ?? 'paid';
        $order->placed_at = now();
        $order->save();

        $order->items()->create([
            'menu_item_id' => $this->menuItem->id,
            'name_snapshot' => $this->menuItem->name,
            'unit_price_snapshot' => 3500,
            'quantity' => 1,
            'line_total' => 3500,
        ]);

        return $order;
    }

    public function test_manager_can_transfer_an_unpaid_cash_order_and_the_total_is_recomputed(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder(); // payment_status pending, cash

        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->closer, 5.5900, -0.1900);

        $response = $this->actingAs($manager)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertSame($this->closer->id, $order->branch_id);
        $this->assertSame($expectedFee, $order->delivery_fee);
        $this->assertSame(3500 + $expectedFee, $order->total);
        $this->assertDatabaseCount('refunds', 0);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'from_status' => 'paid',
            'to_status' => 'paid',
        ]);
    }

    public function test_fee_decrease_on_an_already_paid_order_is_refunded_not_absorbed(): void
    {
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 1]])]);

        $manager = $this->makeManager();
        $order = $this->makeOrder([
            'payment_method' => 'paystack', 'payment_status' => 'paid', 'delivery_fee' => 2500, 'total' => 6000,
        ]);
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'PSK-'.uniqid(),
            'amount' => 6000, 'currency' => 'GHS', 'status' => 'paid', 'verified_at' => now(),
        ]);

        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->closer, 5.5900, -0.1900);
        $this->assertLessThan(2500, $expectedFee, 'test fixture assumes the closer branch prices lower');

        $this->actingAs($manager)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ])->assertOk();

        $order->refresh();
        // Never rewritten — the customer was charged 6000 and that stays
        // the historical fact; the difference comes back as a refund.
        $this->assertSame(6000, $order->total);
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id, 'amount' => 2500 - $expectedFee, 'status' => 'completed',
        ]);
    }

    public function test_fee_increase_on_an_already_paid_order_is_absorbed_not_charged(): void
    {
        $manager = $this->makeManager();
        // Delivery point sits essentially on top of 'closer' and far from
        // 'origin' — flip the eligible destination so the *origin* branch
        // now looks like the "farther" option relative to a even-further
        // third branch. Simplify instead: move the order's own address far
        // from 'closer' so transferring there raises the fee.
        $order = $this->makeOrder([
            'payment_method' => 'paystack', 'payment_status' => 'paid', 'delivery_fee' => 100, 'total' => 3600,
            'delivery_address_snapshot' => ['lat' => 5.5560, 'lng' => -0.1969, 'area_name' => 'Right by origin'],
        ]);
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'PSK-'.uniqid(),
            'amount' => 3600, 'currency' => 'GHS', 'status' => 'paid', 'verified_at' => now(),
        ]);

        $this->actingAs($manager)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ])->assertOk();

        $order->refresh();
        $this->assertSame(3600, $order->total, 'an already-paid order is never charged more after a transfer');
        $this->assertDatabaseCount('refunds', 0);

        $event = $order->events()->where('meta->action', 'branch_transfer')->first();
        $this->assertGreaterThan(0, $event->meta['delivery_fee_absorbed']);
    }

    public function test_staff_can_transfer_but_a_fee_decrease_refund_needs_approval(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makeOrder([
            'payment_method' => 'paystack', 'payment_status' => 'paid', 'delivery_fee' => 2500, 'total' => 6000,
        ]);
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'PSK-'.uniqid(),
            'amount' => 6000, 'currency' => 'GHS', 'status' => 'paid', 'verified_at' => now(),
        ]);

        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->closer, 5.5900, -0.1900);

        $this->actingAs($staff)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ])->assertOk();

        $order->refresh();
        // The order itself moves right away — staff doesn't need approval
        // for that part.
        $this->assertSame($this->closer->id, $order->branch_id);
        $this->assertSame(6000, $order->total);

        // But the resulting refund sits pending, same as any other
        // staff-initiated refund — no Paystack call happens yet.
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id, 'amount' => 2500 - $expectedFee,
            'status' => 'pending', 'requested_by' => $staff->id,
        ]);
    }

    public function test_staff_transfer_with_no_refund_needed_completes_with_nothing_pending(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makeOrder(); // unpaid cash order — no refund involved at all

        $this->actingAs($staff)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ])->assertOk();

        $order->refresh();
        $this->assertSame($this->closer->id, $order->branch_id);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_cannot_transfer_once_preparing(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder(['status' => 'preparing']);

        $this->actingAs($manager)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ])->assertForbidden();
    }

    public function test_cannot_transfer_to_a_branch_that_is_not_accepting_orders(): void
    {
        $this->closer->update(['is_accepting_orders' => false]);

        $manager = $this->makeManager();
        $order = $this->makeOrder();

        $this->actingAs($manager)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ])->assertStatus(422);

        $order->refresh();
        $this->assertSame($this->origin->id, $order->branch_id);
    }

    public function test_cannot_transfer_when_an_item_is_unavailable_at_the_destination(): void
    {
        $this->closer->menuItems()->updateExistingPivot($this->menuItem->id, ['is_available' => false]);

        $manager = $this->makeManager();
        $order = $this->makeOrder();

        $this->actingAs($manager)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->closer->id,
        ])->assertStatus(422);

        $order->refresh();
        $this->assertSame($this->origin->id, $order->branch_id);
    }

    public function test_cannot_transfer_to_the_same_branch(): void
    {
        $manager = $this->makeManager();
        $order = $this->makeOrder();

        $this->actingAs($manager)->postJson(route('orders.transfer_branch', $order), [
            'branch_id' => $this->origin->id,
        ])->assertStatus(422);
    }
}
