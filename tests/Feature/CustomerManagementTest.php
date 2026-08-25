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

class CustomerManagementTest extends TestCase
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

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    private function makeOrder(Customer $customer, string $status, int $total, ?array $deliveryAddress = null, int $minutesAgo = 0): Order
    {
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => $deliveryAddress ? 'delivery' : 'pickup',
            'delivery_address_snapshot' => $deliveryAddress,
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->status = $status;
        $order->placed_at = now()->subMinutes($minutesAgo);
        $order->save();

        return $order;
    }

    public function test_manager_can_view_the_customers_index(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.customers.index'))->assertOk();
    }

    public function test_staff_and_rider_cannot_view_the_customers_index(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->get(route('dashboard.customers.index'))->assertForbidden();

        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);
        $this->actingAs($rider)->get(route('dashboard.customers.index'))->assertForbidden();
    }

    public function test_lifetime_value_excludes_cancelled_and_refunded_orders(): void
    {
        $manager = $this->makeManager();
        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama']);

        $this->makeOrder($customer, 'delivered', 5000);
        $this->makeOrder($customer, 'cancelled', 3000);
        $this->makeOrder($customer, 'refunded', 2000);

        $response = $this->actingAs($manager)->get(route('dashboard.customers.index'));

        $response->assertOk();
        $customers = $response->viewData('customers');
        $found = $customers->firstWhere('id', $customer->id);

        $this->assertSame(3, $found->orders_count);
        $this->assertSame(5000, (int) $found->lifetime_value);
    }

    public function test_location_shows_the_area_from_the_most_recent_delivery_order(): void
    {
        $manager = $this->makeManager();
        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama']);

        $this->makeOrder($customer, 'delivered', 5000, ['area_id' => null, 'area_name' => 'Osu', 'ghanapost_code' => null, 'landmark' => 'Near the mall', 'lat' => null, 'lng' => null], minutesAgo: 120);
        $this->makeOrder($customer, 'delivered', 5000, ['area_id' => null, 'area_name' => 'East Legon', 'ghanapost_code' => null, 'landmark' => 'Near the school', 'lat' => null, 'lng' => null], minutesAgo: 10);

        $response = $this->actingAs($manager)->get(route('dashboard.customers.index'));

        $found = $response->viewData('customers')->firstWhere('id', $customer->id);
        $this->assertSame('East Legon', $found->location);
    }

    public function test_location_is_null_for_a_pickup_only_customer(): void
    {
        $manager = $this->makeManager();
        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama']);

        $this->makeOrder($customer, 'delivered', 5000);

        $response = $this->actingAs($manager)->get(route('dashboard.customers.index'));

        $found = $response->viewData('customers')->firstWhere('id', $customer->id);
        $this->assertNull($found->location);
        $response->assertSee('—');
    }

    public function test_export_includes_the_location_column(): void
    {
        $manager = $this->makeManager();
        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama Mensah']);
        $this->makeOrder($customer, 'delivered', 5000, ['area_id' => null, 'area_name' => 'Osu', 'ghanapost_code' => null, 'landmark' => 'Near the mall', 'lat' => null, 'lng' => null]);

        $response = $this->actingAs($manager)->get(route('dashboard.customers.export'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Location', $content);
        $this->assertStringContainsString('Osu', $content);
    }

    public function test_search_filters_by_name_or_phone(): void
    {
        $manager = $this->makeManager();
        Customer::create(['phone' => '+233241111111', 'name' => 'Ama Mensah']);
        Customer::create(['phone' => '+233242222222', 'name' => 'Kwame Boateng']);

        $response = $this->actingAs($manager)->get(route('dashboard.customers.index', ['search' => 'Ama']));

        $response->assertOk()->assertSee('Ama Mensah')->assertDontSee('Kwame Boateng');
    }

    public function test_export_returns_a_csv_with_the_right_rows(): void
    {
        $manager = $this->makeManager();
        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama Mensah']);
        $this->makeOrder($customer, 'delivered', 5000);

        $response = $this->actingAs($manager)->get(route('dashboard.customers.export'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Ama Mensah', $content);
        $this->assertStringContainsString('+233241111111', $content);
        $this->assertStringContainsString('50.00', $content);
    }
}
