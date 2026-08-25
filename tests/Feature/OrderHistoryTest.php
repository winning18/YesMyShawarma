<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
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

    private function makeOrder(
        string $status,
        string $channel,
        ?Customer $customer = null,
        string $fulfilmentType = 'pickup',
        ?array $deliveryAddress = null,
        int $minutesAgo = 0,
    ): Order {
        $customer ??= Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => $fulfilmentType,
            'delivery_address_snapshot' => $deliveryAddress,
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'channel' => $channel,
        ]);
        $order->status = $status;
        $order->placed_at = now('Africa/Accra')->subMinutes($minutesAgo);
        $order->save();

        return $order;
    }

    public function test_staff_can_view_order_history(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.orders.history'))->assertOk();
    }

    public function test_order_history_includes_the_order_alert_widget_for_staff(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.orders.history'))
            ->assertSee("orderAlertWidget({$this->branch->id})", false);
    }

    public function test_order_history_hides_the_order_alert_widget_from_owner(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        // Owner never operates the live board — hideOperationalControls
        // drops the alert (and its Echo subscription) along with the
        // shift widget and Orders/POS toggle, same reasoning throughout
        // dashboard/_channel-header.blade.php.
        $this->actingAs($owner)->get(route('dashboard.orders.history'))
            ->assertDontSee('orderAlertWidget', false);
    }

    public function test_history_includes_completed_orders_unlike_the_live_board(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $delivered = $this->makeOrder('delivered', 'web');
        $cancelled = $this->makeOrder('cancelled', 'web');

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history'));

        $orders = $response->viewData('orders');
        $ids = collect($orders->items())->pluck('id');

        $this->assertTrue($ids->contains($delivered->id));
        $this->assertTrue($ids->contains($cancelled->id));
    }

    public function test_channel_filter_shows_only_that_channels_history(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $webOrder = $this->makeOrder('delivered', 'web');
        $posOrder = $this->makeOrder('delivered', 'pos');

        $webResponse = $this->actingAs($staff)->get(route('dashboard.orders.history', ['channel' => 'web']));
        $webIds = collect($webResponse->viewData('orders')->items())->pluck('id');
        $this->assertTrue($webIds->contains($webOrder->id));
        $this->assertFalse($webIds->contains($posOrder->id));

        $posResponse = $this->actingAs($staff)->get(route('dashboard.orders.history', ['channel' => 'pos']));
        $posIds = collect($posResponse->viewData('orders')->items())->pluck('id');
        $this->assertTrue($posIds->contains($posOrder->id));
        $this->assertFalse($posIds->contains($webOrder->id));
    }

    public function test_search_filters_by_customer_name(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $ama = Customer::create(['phone' => '+233241111111', 'name' => 'Ama Mensah']);
        $kwame = Customer::create(['phone' => '+233242222222', 'name' => 'Kwame Boateng']);
        $amaOrder = $this->makeOrder('delivered', 'web', $ama);
        $this->makeOrder('delivered', 'web', $kwame);

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', ['search' => 'Ama']));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertTrue($ids->contains($amaOrder->id));
        $this->assertCount(1, $ids);
    }

    public function test_search_filters_by_reference(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $order = $this->makeOrder('delivered', 'web');
        $this->makeOrder('delivered', 'web');

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', ['search' => $order->reference]));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$order->id], $ids->all());
    }

    public function test_status_filter(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $delivered = $this->makeOrder('delivered', 'web');
        $this->makeOrder('cancelled', 'web');

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', ['status' => 'delivered']));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$delivered->id], $ids->all());
    }

    public function test_fulfilment_type_filter(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $delivery = $this->makeOrder('delivered', 'web', fulfilmentType: 'delivery');
        $this->makeOrder('delivered', 'web', fulfilmentType: 'pickup');

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', ['fulfilment_type' => 'delivery']));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$delivery->id], $ids->all());
    }

    public function test_location_filter(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $osuOrder = $this->makeOrder('delivered', 'web', fulfilmentType: 'delivery', deliveryAddress: [
            'area_id' => null, 'area_name' => 'Osu', 'ghanapost_code' => null, 'landmark' => 'Near the mall', 'lat' => null, 'lng' => null,
        ]);
        $this->makeOrder('delivered', 'web', fulfilmentType: 'delivery', deliveryAddress: [
            'area_id' => null, 'area_name' => 'East Legon', 'ghanapost_code' => null, 'landmark' => 'Near the school', 'lat' => null, 'lng' => null,
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', ['location' => 'Osu']));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$osuOrder->id], $ids->all());
    }

    public function test_range_today_excludes_orders_from_other_days(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $today = $this->makeOrder('delivered', 'web', minutesAgo: 5);
        $this->makeOrder('delivered', 'web', minutesAgo: 60 * 24 * 3);

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', ['range' => 'today']));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$today->id], $ids->all());
    }

    public function test_range_last_7_days_excludes_older_orders(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $recent = $this->makeOrder('delivered', 'web', minutesAgo: 60 * 24 * 2);
        $this->makeOrder('delivered', 'web', minutesAgo: 60 * 24 * 10);

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', ['range' => '7']));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$recent->id], $ids->all());
    }

    public function test_custom_range_filters_between_from_and_to(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $inRange = $this->makeOrder('delivered', 'web', minutesAgo: 60 * 24 * 5);
        $this->makeOrder('delivered', 'web', minutesAgo: 60 * 24 * 20);

        $from = now('Africa/Accra')->subDays(7)->format('Y-m-d\TH:i');
        $to = now('Africa/Accra')->format('Y-m-d\TH:i');

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', [
            'range' => 'custom', 'from' => $from, 'to' => $to,
        ]));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$inRange->id], $ids->all());
    }

    public function test_filters_combine_with_the_channel_filter(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $webDelivered = $this->makeOrder('delivered', 'web');
        $this->makeOrder('delivered', 'pos');
        $this->makeOrder('cancelled', 'web');

        $response = $this->actingAs($staff)->get(route('dashboard.orders.history', [
            'channel' => 'web', 'status' => 'delivered',
        ]));

        $ids = collect($response->viewData('orders')->items())->pluck('id');
        $this->assertSame([$webDelivered->id], $ids->all());
    }

    public function test_history_rows_link_to_the_order_detail_page(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $order = $this->makeOrder('delivered', 'web');

        $this->actingAs($staff)->get(route('dashboard.orders.history'))
            ->assertSee(route('dashboard.orders.show', $order), false);
    }

    public function test_order_detail_page_shows_customer_information(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama Mensah', 'email' => 'ama@example.com']);
        $order = $this->makeOrder('delivered', 'web', $customer);

        $response = $this->actingAs($staff)->get(route('dashboard.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Ama Mensah');
        $response->assertSee('+233241111111');
        $response->assertSee('ama@example.com');
        $response->assertSee($order->reference);
    }

    public function test_order_detail_page_shows_items_and_totals(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $menuItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);

        $order = $this->makeOrder('delivered', 'web');
        $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'name_snapshot' => 'Chicken Shawarma',
            'unit_price_snapshot' => 5000,
            'quantity' => 1,
            'line_total' => 5000,
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Chicken Shawarma');
        $response->assertSee('50.00');
    }

    public function test_rider_cannot_view_an_order_from_another_branch(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);
        $otherOrder = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $otherBranch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'channel' => 'web',
        ]);
        $otherOrder->status = 'delivered';
        $otherOrder->placed_at = now();
        $otherOrder->save();

        // OrderPolicy::view() checks against the order's OWN branch, not the
        // rider's session branch — the rider holds no role at otherBranch,
        // so this is blocked regardless of BranchScope's own filtering.
        $this->actingAs($rider)->get(route('dashboard.orders.show', $otherOrder))->assertForbidden();
    }
}
