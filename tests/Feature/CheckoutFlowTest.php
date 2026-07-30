<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
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

    public function test_cash_checkout_creates_order_clears_cart_and_redirects_to_tracking(): void
    {
        $this->addToCart(2);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
        ]);

        $order = Order::first();

        $response->assertRedirect(route('tracking.show', $order));

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

    public function test_delivery_is_forced_to_pickup_when_branch_has_no_delivery_zones(): void
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

    public function test_checkout_page_offers_a_drink_upsell_and_adding_one_updates_the_cart(): void
    {
        $drinksCategory = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $mango = MenuItem::create([
            'category_id' => $drinksCategory->id, 'name' => 'Mango', 'slug' => 'mango', 'base_price' => 2000,
        ]);
        $this->branch->menuItems()->attach($mango->id, ['is_available' => true]);

        $this->addToCart(1);

        $this->get(route('checkout.show'))->assertOk()->assertSee('Mango');

        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $mango->id,
            'quantity' => 1,
            'option_ids' => [],
        ]);

        $this->post(route('checkout.store'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'payment_method' => 'cash',
        ]);

        $order = Order::first();
        $this->assertSame(7000, $order->total); // 5000 shawarma + 2000 mango
        $this->assertCount(2, $order->items);
    }
}
