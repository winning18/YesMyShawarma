<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(?string $customerName = null): Order
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $customer = Customer::create(['phone' => '+233241111111', 'name' => $customerName]);

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

    public function test_tracking_data_includes_rider_contact_once_assigned(): void
    {
        $order = $this->makeOrder();
        $rider = User::create(['name' => 'Kwame', 'email' => 'kwame@example.com', 'phone' => '+233240000000', 'password' => 'secret']);
        $order->rider_id = $rider->id;
        $order->save();

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()
            ->assertJsonPath('data.rider.name', 'Kwame')
            ->assertJsonPath('data.rider.phone', '+233240000000');
    }

    public function test_tracking_data_has_no_rider_when_unassigned(): void
    {
        $order = $this->makeOrder();

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()->assertJsonPath('data.rider', null);
    }

    public function test_tracking_data_includes_customer_name_and_phone(): void
    {
        $order = $this->makeOrder(customerName: 'Ama');

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()
            ->assertJsonPath('data.customer.name', 'Ama')
            ->assertJsonPath('data.customer.phone', '+233241111111');
    }

    public function test_tracking_data_includes_item_image_url(): void
    {
        $order = $this->makeOrder();
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Chicken Shawarma',
            'slug' => 'chicken-shawarma',
            'base_price' => 3500,
            'image_path' => 'menu-items/shawarma.jpg',
        ]);
        $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'name_snapshot' => 'Chicken Shawarma',
            'unit_price_snapshot' => 3500,
            'quantity' => 1,
            'line_total' => 3500,
        ]);

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()
            ->assertJsonPath('data.items.0.image_url', fn ($url) => str_contains($url, 'menu-items/shawarma.jpg'));
    }

    public function test_tracking_data_includes_pricing_breakdown_and_payment_method(): void
    {
        $order = $this->makeOrder();
        $order->update(['discount_total' => 500, 'total' => 3000]);

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()
            ->assertJsonPath('data.subtotal', 3500)
            ->assertJsonPath('data.discount_total', 500)
            ->assertJsonPath('data.delivery_fee', 0)
            ->assertJsonPath('data.total', 3000)
            ->assertJsonPath('data.payment_method', 'cash');
    }

    public function test_tracking_data_includes_delivery_address_for_delivery_orders(): void
    {
        $order = $this->makeOrder();
        $order->update([
            'fulfilment_type' => 'delivery',
            'delivery_address_snapshot' => ['area_name' => 'Osu', 'landmark' => 'Near the mall', 'lat' => null, 'lng' => null, 'ghanapost_code' => null, 'area_id' => null],
        ]);

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()
            ->assertJsonPath('data.delivery_address.area_name', 'Osu')
            ->assertJsonPath('data.delivery_address.landmark', 'Near the mall');
    }

    public function test_tracking_data_has_no_delivery_address_for_pickup_orders(): void
    {
        $order = $this->makeOrder();

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()->assertJsonPath('data.delivery_address', null);
    }

    public function test_tracking_data_includes_item_options(): void
    {
        $order = $this->makeOrder();
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Chicken Shawarma',
            'slug' => 'chicken-shawarma',
            'base_price' => 3500,
        ]);
        $item = $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'name_snapshot' => 'Chicken Shawarma',
            'unit_price_snapshot' => 3500,
            'quantity' => 1,
            'line_total' => 3700,
        ]);
        $sauceGroup = OptionGroup::create(['name' => 'Sauce', 'min_select' => 0, 'max_select' => 1, 'is_required' => false]);
        $chiliSauce = Option::create(['option_group_id' => $sauceGroup->id, 'name' => 'Chili', 'price_delta' => 200]);
        $item->options()->create(['option_id' => $chiliSauce->id, 'name_snapshot' => 'Chili', 'price_delta_snapshot' => 200]);

        $response = $this->getJson(route('tracking.data', $order));

        $response->assertOk()
            ->assertJsonPath('data.items.0.line_total', 3700)
            ->assertJsonPath('data.items.0.options.0.name', 'Chili')
            ->assertJsonPath('data.items.0.options.0.price_delta', 200);
    }
}
