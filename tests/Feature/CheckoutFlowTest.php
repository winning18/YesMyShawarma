<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Delivery\DeliveryFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $shawarma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);

        $this->shawarma = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->branch->menuItems()->attach($this->shawarma->id, ['is_available' => true]);
    }

    private function addToCart(int $quantity = 1): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => $quantity,
            'option_ids' => [],
        ]);
    }

    public function test_cash_checkout_creates_order_clears_cart_and_redirects_to_confirmation(): void
    {
        $this->addToCart(2);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
        ]);

        $order = Order::first();

        $response->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame('paid', $order->status);
        $this->assertSame('pickup', $order->fulfilment_type);
        $this->assertSame(10000, $order->total);
        $this->assertSame('+233241111111', $order->customer->phone);

        $this->get(route('cart.show'))->assertSee('Your cart is empty');
    }

    public function test_paystack_checkout_redirects_to_paystack_authorization_url(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'whatever'],
            ], 200),
        ]);

        $this->addToCart(1);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'paystack',
        ]);

        $response->assertRedirect('https://checkout.paystack.com/xyz');

        $order = Order::first();
        $this->assertSame('pending_payment', $order->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'paystack', 'status' => 'pending']);
    }

    public function test_checkout_with_empty_cart_redirects_back_to_cart(): void
    {
        $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
        ])->assertRedirect(route('cart.show'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_is_forced_to_pickup_when_no_delivery_areas_exist(): void
    {
        $this->addToCart(1);

        $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
            'fulfilment_type' => 'delivery',
        ]);

        $this->assertSame('pickup', Order::first()->fulfilment_type);
    }

    public function test_delivery_checkout_with_a_selected_area_and_instructions_succeeds(): void
    {
        $area = DeliveryArea::create(['name' => 'Osu']);

        $this->addToCart(1);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
            'fulfilment_type' => 'delivery',
            'area_id' => $area->id,
            'ghanapost_code' => 'GA-123-4567',
            'landmark' => 'Near the mall',
            'lat' => 5.5010,
            'lng' => -0.1010,
            'instructions' => 'Call on arrival',
        ]);

        $order = Order::first();
        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->branch, 5.5010, -0.1010);

        $response->assertRedirect(route('checkout.confirmation', $order));
        $this->assertSame('delivery', $order->fulfilment_type);
        // Location was captured, so the fee is priced immediately rather
        // than deferred to the delivered transition.
        $this->assertSame($expectedFee, $order->delivery_fee);
        $this->assertSame($area->id, $order->delivery_address_snapshot['area_id']);
        $this->assertSame(5.5010, $order->delivery_address_snapshot['lat']);
        $this->assertSame('Call on arrival', $order->instructions);
    }

    public function test_delivery_checkout_without_a_selected_area_is_rejected(): void
    {
        DeliveryArea::create(['name' => 'Osu']);

        $this->addToCart(1);

        $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
            'fulfilment_type' => 'delivery',
            'ghanapost_code' => 'GA-123-4567',
            'landmark' => 'Near the mall',
            'lat' => 5.5010,
            'lng' => -0.1010,
        ])->assertSessionHasErrors('area_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_checkout_without_live_location_defers_the_fee(): void
    {
        $area = DeliveryArea::create(['name' => 'Osu']);

        $this->addToCart(1);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
            'fulfilment_type' => 'delivery',
            'area_id' => $area->id,
            'ghanapost_code' => 'GA-123-4567',
            'landmark' => 'Near the mall',
        ]);

        $order = Order::first();

        $response->assertRedirect(route('checkout.confirmation', $order));
        $this->assertSame('delivery', $order->fulfilment_type);
        $this->assertSame(0, $order->delivery_fee);
        $this->assertNull($order->delivery_address_snapshot['lat']);
    }

    public function test_paystack_is_allowed_for_delivery_checkout(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'whatever'],
            ], 200),
        ]);

        $area = DeliveryArea::create(['name' => 'Osu']);

        $this->addToCart(1);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'paystack',
            'fulfilment_type' => 'delivery',
            'area_id' => $area->id,
            'ghanapost_code' => 'GA-123-4567',
            'landmark' => 'Near the mall',
            'lat' => 5.5010,
            'lng' => -0.1010,
        ]);

        $response->assertRedirect('https://checkout.paystack.com/xyz');

        $order = Order::first();
        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->branch, 5.5010, -0.1010);

        $this->assertSame('delivery', $order->fulfilment_type);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame($expectedFee, $order->delivery_fee);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'paystack', 'status' => 'pending']);
    }

    public function test_delivery_checkout_with_an_unlisted_area_creates_it_via_free_text(): void
    {
        DeliveryArea::create(['name' => 'Osu']);

        $this->addToCart(1);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
            'fulfilment_type' => 'delivery',
            'area_id' => 'other',
            'area_other' => 'East Legon',
            'ghanapost_code' => 'GA-123-4567',
            'landmark' => 'Near the mall',
            'lat' => 5.5010,
            'lng' => -0.1010,
        ]);

        $order = Order::first();

        $response->assertRedirect(route('checkout.confirmation', $order));
        $this->assertDatabaseHas('delivery_areas', ['name' => 'East Legon']);
        $this->assertSame('East Legon', $order->delivery_address_snapshot['area_name']);
    }

    public function test_confirmation_page_shows_the_order_summary(): void
    {
        $this->addToCart(2);

        $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
        ]);

        $order = Order::first();

        $this->get(route('checkout.confirmation', $order))
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee('+233241111111')
            ->assertSee('2x Chicken Shawarma')
            ->assertSee('GH₵100.00');
    }
}
