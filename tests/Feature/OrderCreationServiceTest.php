<?php

namespace Tests\Feature;

use App\Contracts\Notifier;
use App\Events\OrderPlaced;
use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Promotion;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreationService $service;

    private Branch $branch;

    private MenuItem $shawarma;

    private DeliveryArea $area;

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

        $this->area = DeliveryArea::create(['name' => 'Osu']);

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
        // Pickup cash is reconciled the instant staff take it — see
        // payments.md's "Cash" section — unlike delivery cash, which stays
        // pending until the rider confirms (OrderStateMachineTest).
        $this->assertSame('paid', $order->payment_status);
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
            'order_id' => $order->id, 'provider' => 'cash', 'amount' => 7400, 'status' => 'paid',
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'from_status' => null, 'to_status' => 'paid', 'actor_type' => 'customer',
        ]);
    }

    public function test_placing_an_order_dispatches_order_placed_for_the_live_dashboard(): void
    {
        Event::fake([OrderPlaced::class]);

        $order = $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->chiliSauce->id])],
        ));

        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->orderId === $order->id && $event->branchId === $this->branch->id
        );
    }

    public function test_a_manually_settled_order_sends_the_customer_a_placed_sms(): void
    {
        // Cash/momo orders are created already-'paid' — the "placed" SMS
        // fires right here, not from OrderStateMachine (that's the Paystack
        // path, see OrderStateMachineTest). Deferred to the transaction's
        // commit, same as the OrderPlaced broadcast just above it.
        $this->mock(Notifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once()
                ->with('+233241111111', \Mockery::type('string'), \Mockery::type('array'));
        });

        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->chiliSauce->id])],
        ));
    }

    public function test_a_paystack_order_does_not_send_a_placed_sms_yet(): void
    {
        // Still pending_payment at creation — the "placed" SMS only fires
        // once the webhook actually confirms payment (OrderStateMachine).
        $this->mock(Notifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'paystack',
            items: [$this->baseItem([$this->chiliSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                areaId: $this->area->id,
                ghanapostCode: 'GA-123-4567',
                landmark: 'Near the mall',
                lat: 5.5565,
                lng: -0.1970,
            ),
        ));
    }

    public function test_web_order_reference_uses_the_default_web_prefix(): void
    {
        $order = $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            channel: 'web',
        ));

        $this->assertMatchesRegularExpression('/^YMGS-WEB-[A-Z0-9]{6}$/', $order->reference);
    }

    public function test_pos_order_reference_uses_the_default_pos_prefix(): void
    {
        $order = $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            channel: 'pos',
        ));

        $this->assertMatchesRegularExpression('/^YMGS-POS-[A-Z0-9]{6}$/', $order->reference);
    }

    public function test_order_reference_uses_the_configured_prefix_once_settings_are_changed(): void
    {
        app(SettingsService::class)->set(SettingsService::ORDER_REFERENCE_PREFIX_WEB, 'SHAWARMA-ONLINE');

        $order = $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            channel: 'web',
        ));

        $this->assertMatchesRegularExpression('/^SHAWARMA-ONLINE-[A-Z0-9]{6}$/', $order->reference);
    }

    public function test_pickup_momo_order_succeeds_end_to_end(): void
    {
        // Momo is an in-house manual payment (Order::MANUALLY_SETTLED_
        // PAYMENT_METHODS) — same immediate-'paid' path as cash, no
        // Paystack involved.
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'momo',
            items: [$this->baseItem([$this->garlicSauce->id])],
        );

        $order = $this->service->create($data);

        $this->assertSame('paid', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('momo', $order->payment_method);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'momo', 'amount' => $order->total, 'status' => 'pending',
        ]);
    }

    public function test_delivery_cash_order_prices_the_fee_immediately_when_location_is_captured(): void
    {
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                areaId: $this->area->id,
                ghanapostCode: 'GA-123-4567',
                landmark: 'Near the mall',
                lat: 5.5565,
                lng: -0.1970,
            ),
        );

        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->branch, 5.5565, -0.1970);

        $order = $this->service->create($data);

        $this->assertSame(7000, $order->subtotal); // 3500 * 2, no chili
        $this->assertGreaterThan(0, $expectedFee); // sanity check the test points aren't identical
        $this->assertSame($expectedFee, $order->delivery_fee);
        $this->assertSame(7000 + $expectedFee, $order->total);
        $this->assertSame('paid', $order->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'cash', 'amount' => 7000 + $expectedFee,
        ]);
        $this->assertSame('GA-123-4567', $order->delivery_address_snapshot['ghanapost_code']);
        $this->assertSame($this->area->id, $order->delivery_address_snapshot['area_id']);
        $this->assertSame(5.5565, $order->delivery_address_snapshot['lat']);
    }

    public function test_delivery_cash_order_defers_the_fee_when_location_is_not_captured(): void
    {
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                areaId: $this->area->id,
                ghanapostCode: 'GA-123-4567',
                landmark: 'Near the mall',
                lat: null,
                lng: null,
            ),
        );

        $order = $this->service->create($data);

        // Fee isn't known yet — priced later, at the delivered transition
        // (see OrderStateMachineTest), once location is available another way.
        $this->assertSame(0, $order->delivery_fee);
        $this->assertSame(7000, $order->total);
        $this->assertNull($order->delivery_address_snapshot['lat']);
    }

    public function test_delivery_cash_order_defers_payment_status_until_the_rider_confirms(): void
    {
        // Unlike pickup cash (paid the instant staff take it), nobody has
        // been paid yet for a delivery order at placement time — the rider
        // collects it at the door. See OrderStateMachineTest for the
        // delivered-transition side of this.
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                areaId: $this->area->id,
                ghanapostCode: 'GA-123-4567',
                landmark: 'Near the mall',
                lat: 5.5565,
                lng: -0.1970,
            ),
        );

        $order = $this->service->create($data);

        $this->assertSame('paid', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'cash', 'status' => 'pending',
        ]);
    }

    public function test_momo_order_with_a_transaction_id_is_paid_immediately(): void
    {
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'momo',
            items: [$this->baseItem([$this->garlicSauce->id])],
            paymentReference: 'MOMO-TXN-12345',
        );

        $order = $this->service->create($data);

        $this->assertSame('paid', $order->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'momo',
            'provider_reference' => 'MOMO-TXN-12345', 'status' => 'paid',
        ]);
    }

    public function test_paystack_is_allowed_for_delivery_orders(): void
    {
        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'paystack',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                areaId: $this->area->id,
                ghanapostCode: 'GA-123-4567',
                landmark: 'Near the mall',
                lat: 5.5565,
                lng: -0.1970,
            ),
        );

        $expectedFee = app(DeliveryFeeCalculator::class)->calculate($this->branch, 5.5565, -0.1970);

        $order = $this->service->create($data);

        $this->assertSame('paystack', $order->payment_method);
        $this->assertSame($expectedFee, $order->delivery_fee);
        $this->assertSame(7000 + $expectedFee, $order->total);
        $this->assertSame('pending_payment', $order->status);

        // No cash payments row — Paystack's own init flow creates its payment record.
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id, 'provider' => 'cash']);
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

    public function test_an_inactive_delivery_area_is_rejected(): void
    {
        $this->area->update(['is_active' => false]);

        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                areaId: $this->area->id,
                ghanapostCode: 'GA-999-9999',
                landmark: 'Far away',
                lat: 5.5565,
                lng: -0.1970,
            ),
        ));
    }

    public function test_an_unlisted_area_creates_a_new_delivery_area_via_free_text(): void
    {
        $this->assertDatabaseMissing('delivery_areas', ['name' => 'East Legon']);

        $data = new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            deliveryAddress: new DeliveryAddressData(
                areaId: null,
                ghanapostCode: null,
                landmark: 'Near the mall',
                lat: 5.5565,
                lng: -0.1970,
                areaOther: '  East Legon  ',
            ),
        );

        $order = $this->service->create($data);

        $this->assertDatabaseHas('delivery_areas', ['name' => 'East Legon']);
        $this->assertSame('East Legon', $order->delivery_address_snapshot['area_name']);
    }

    public function test_a_valid_promo_code_discounts_the_order_and_records_a_redemption(): void
    {
        Promotion::create(['code' => 'TENOFF', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);

        $order = $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            promoCode: 'TENOFF',
        ));

        // (3500 + 0 garlic) * 2 = 7000, 10% off = 700.
        $this->assertSame(7000, $order->subtotal);
        $this->assertSame(700, $order->discount_total);
        $this->assertSame(6300, $order->total);
        $this->assertNotNull($order->promotion_id);

        $this->assertDatabaseHas('promotion_redemptions', [
            'order_id' => $order->id,
            'amount_discounted' => 700,
        ]);
    }

    public function test_an_invalid_promo_code_is_rejected(): void
    {
        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [$this->baseItem([$this->garlicSauce->id])],
            promoCode: 'DOES-NOT-EXIST',
        ));
    }

    public function test_order_below_the_minimum_total_is_rejected(): void
    {
        $cheapItem = MenuItem::create([
            'category_id' => $this->shawarma->category_id,
            'name' => 'Small Drink', 'slug' => 'small-drink', 'base_price' => 1000,
        ]);
        $this->branch->menuItems()->attach($cheapItem->id, ['is_available' => true]);

        $this->expectException(OrderPlacementException::class);

        $this->service->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: null,
            branchId: $this->branch->id,
            fulfilmentType: 'delivery',
            paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $cheapItem->id, quantity: 1, optionIds: [])],
            deliveryAddress: new DeliveryAddressData(
                areaId: $this->area->id,
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
