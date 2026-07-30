<?php

namespace Tests\Feature;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreationService $service;

    private Branch $branch;

    private MenuItem $shawarma;

    private OptionGroup $sauceGroup;

    private Option $garlicSauce;

    private Option $chiliSauce;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderCreationService::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5560, 'lng' => -0.1969, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        DeliveryZone::create([
            'branch_id' => $this->branch->id,
            'name' => 'Osu Zone',
            'delivery_fee' => 1000,
            'min_order_total' => 2000,
            'radius_metres' => 5000,
            'centre_lat' => 5.5560,
            'centre_lng' => -0.1969,
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);

        $this->shawarma = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Chicken Shawarma',
            'slug' => 'chicken-shawarma',
            'base_price' => 3500,
        ]);

        $this->branch->menuItems()->attach($this->shawarma->id, ['is_available' => true]);

        $this->sauceGroup = OptionGroup::create([
            'name' => 'Sauce', 'min_select' => 1, 'max_select' => 1, 'is_required' => true,
        ]);
        $this->garlicSauce = Option::create(['option_group_id' => $this->sauceGroup->id, 'name' => 'Garlic', 'price_delta' => 0]);
        $this->chiliSauce = Option::create(['option_group_id' => $this->sauceGroup->id, 'name' => 'Chili', 'price_delta' => 200]);

        $this->shawarma->optionGroups()->attach($this->sauceGroup->id, ['sort_order' => 1]);
    }

    private function baseItem(array $optionIds): PlaceOrderItemData
    {
        return new PlaceOrderItemData(
            menuItemId: $this->shawarma->id,
            quantity: 2,
            optionIds: $optionIds,
        );
    }

    public function test_pickup_cash_order_succeeds_end_to_end(): void
    {
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->chiliSauce->id])],
        );

        $order = $this->service->create($data);

        // (3500 + 200 chili) * 2 = 7400, no delivery fee for pickup.
        $this->assertSame(7400, $order->subtotal);
        $this->assertSame(0, $order->delivery_fee);
        $this->assertSame(7400, $order->total);
        $this->assertSame('paid', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotNull($order->placed_at);
        $this->assertNull($order->delivery_address_snapshot);

        $this->assertSame('Ama', $order->customer->name);
        $this->assertSame('+233241111111', $order->customer->phone);

        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame('Chicken Shawarma', $item->name_snapshot);
        $this->assertSame(3500, $item->unit_price_snapshot);
        $this->assertCount(1, $item->options);
        $this->assertSame('Chili', $item->options->first()->name_snapshot);
        $this->assertSame(200, $item->options->first()->price_delta_snapshot);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'cash', 'amount' => 7400, 'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'from_status' => null, 'to_status' => 'paid', 'actor_type' => 'customer',
        ]);
    }

    public function test_delivery_paystack_order_computes_zone_fee_and_stays_pending(): void
    {
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'paystack',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                ghanapostCode: 'GA-123-4567',
                landmark: 'Near the mall',
                lat: 5.5565,
                lng: -0.1970,
            ),
        );

        $order = $this->service->create($data);

        $this->assertSame(7000, $order->subtotal); // 3500 * 2, no chili
        $this->assertSame(1000, $order->delivery_fee);
        $this->assertSame(8000, $order->total);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('GA-123-4567', $order->delivery_address_snapshot['ghanapost_code']);

        // No payments row yet — Paystack init is a separate service.
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_repeat_customer_reuses_the_same_customer_row(): void
    {
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
        );

        $first = $this->service->create($data);
        $second = $this->service->create($data);

        $this->assertSame($first->customer_id, $second->customer_id);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_branch_not_accepting_orders_is_rejected(): void
    {
        $this->branch->update(['is_accepting_orders' => false]);

        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
        ));
    }

    public function test_unavailable_menu_item_is_rejected(): void
    {
        $this->branch->menuItems()->updateExistingPivot($this->shawarma->id, ['is_available' => false]);

        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
        ));
    }

    public function test_missing_required_option_selection_is_rejected(): void
    {
        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([])],
        ));
    }

    public function test_address_outside_delivery_zone_is_rejected(): void
    {
        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'paystack',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                ghanapostCode: 'GA-999-9999',
                landmark: 'Far away',
                lat: 6.6000,
                lng: -1.6000,
            ),
        ));
    }

    public function test_order_below_zone_minimum_is_rejected(): void
    {
        DeliveryZone::query()->update(['min_order_total' => 10000]);

        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'paystack',
            items: [new PlaceOrderItemData(menuItemId: $this->shawarma->id, quantity: 1, optionIds: [$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                ghanapostCode: 'GA-123-4567',
                landmark: 'Near the mall',
                lat: 5.5565,
                lng: -0.1970,
            ),
        ));
    }

    public function test_empty_cart_is_rejected(): void
    {
        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [],
        ));
    }
}
