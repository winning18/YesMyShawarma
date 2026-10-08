<?php

namespace Tests\Feature;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use App\Services\Promotions\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PromotionService::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->customer = Customer::create(['phone' => '+233241111111']);
    }

    private function makePromotion(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'code' => 'WELCOME10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_a_valid_code_passes(): void
    {
        $this->makePromotion();

        $promotion = $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);

        $this->assertSame('WELCOME10', $promotion->code);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->expectException(OrderPlacementException::class);

        $this->service->validate('NOPE', $this->branch, $this->customer, 10000);
    }

    public function test_an_inactive_promotion_is_rejected(): void
    {
        $this->makePromotion(['is_active' => false]);

        $this->expectException(OrderPlacementException::class);

        $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);
    }

    public function test_a_not_yet_started_promotion_is_rejected(): void
    {
        $this->makePromotion(['starts_at' => now()->addDay()]);

        $this->expectException(OrderPlacementException::class);

        $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);
    }

    public function test_an_expired_promotion_is_rejected(): void
    {
        $this->makePromotion(['ends_at' => now()->subDay()]);

        $this->expectException(OrderPlacementException::class);

        $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);
    }

    public function test_a_promotion_restricted_to_another_branch_is_rejected(): void
    {
        $promotion = $this->makePromotion();
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $promotion->branches()->attach($otherBranch->id);

        $this->expectException(OrderPlacementException::class);

        $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);
    }

    public function test_a_promotion_restricted_to_this_branch_passes(): void
    {
        $promotion = $this->makePromotion();
        $promotion->branches()->attach($this->branch->id);

        $result = $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);

        $this->assertSame($promotion->id, $result->id);
    }

    public function test_a_promotion_with_no_branch_restriction_applies_everywhere(): void
    {
        $this->makePromotion();

        $result = $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);

        $this->assertNotNull($result);
    }

    public function test_subtotal_below_minimum_is_rejected(): void
    {
        $this->makePromotion(['min_order_total' => 5000]);

        $this->expectException(OrderPlacementException::class);

        $this->service->validate('WELCOME10', $this->branch, $this->customer, 4999);
    }

    public function test_subtotal_at_minimum_passes(): void
    {
        $this->makePromotion(['min_order_total' => 5000]);

        $result = $this->service->validate('WELCOME10', $this->branch, $this->customer, 5000);

        $this->assertNotNull($result);
    }

    public function test_max_redemptions_reached_is_rejected(): void
    {
        $promotion = $this->makePromotion(['max_redemptions' => 1]);
        $order = $this->makeOrder();
        $promotion->redemptions()->create([
            'order_id' => $order->id, 'customer_id' => $this->customer->id, 'amount_discounted' => 1000,
        ]);

        $this->expectException(OrderPlacementException::class);

        $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);
    }

    public function test_max_per_customer_reached_is_rejected_for_that_customer_only(): void
    {
        $promotion = $this->makePromotion(['max_per_customer' => 1]);
        $order = $this->makeOrder();
        $promotion->redemptions()->create([
            'order_id' => $order->id, 'customer_id' => $this->customer->id, 'amount_discounted' => 1000,
        ]);

        $otherCustomer = Customer::create(['phone' => '+233242222222']);

        $this->expectException(OrderPlacementException::class);
        $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);
    }

    public function test_an_unsaved_customer_has_no_redemptions(): void
    {
        $promotion = $this->makePromotion(['max_per_customer' => 1]);
        $transientCustomer = new Customer(['phone' => '+233249999999']);

        $result = $this->service->validate('WELCOME10', $this->branch, $transientCustomer, 10000);

        $this->assertSame($promotion->id, $result->id);
    }

    public function test_percentage_discount_is_calculated_correctly(): void
    {
        $promotion = $this->makePromotion(['type' => 'percentage', 'value' => 10]);

        $this->assertSame(1000, $this->service->calculateDiscount($promotion, 10000));
    }

    public function test_fixed_discount_is_calculated_correctly(): void
    {
        $promotion = $this->makePromotion(['type' => 'fixed', 'value' => 500]);

        $this->assertSame(500, $this->service->calculateDiscount($promotion, 10000));
    }

    public function test_discount_is_capped_at_subtotal(): void
    {
        $promotion = $this->makePromotion(['type' => 'fixed', 'value' => 5000]);

        $this->assertSame(3000, $this->service->calculateDiscount($promotion, 3000));
    }

    public function test_buy_x_get_y_free_discounts_one_free_unit_per_group_of_three(): void
    {
        $promotion = $this->makePromotion(['type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 3, 'free_quantity' => 1]);
        $itemRows = [['menu_item_id' => 1, 'unit_price_snapshot' => 1000, 'quantity' => 3]];

        $this->assertSame(1000, $this->service->calculateDiscount($promotion, 3000, $itemRows));
    }

    public function test_buy_x_get_y_free_repeats_per_group_rather_than_capping_at_one(): void
    {
        $promotion = $this->makePromotion(['type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 3, 'free_quantity' => 1]);
        // 7 units: floor(7/3) = 2 free, not capped at 1 and not rounded up to 3.
        $itemRows = [['menu_item_id' => 1, 'unit_price_snapshot' => 1000, 'quantity' => 7]];

        $this->assertSame(2000, $this->service->calculateDiscount($promotion, 7000, $itemRows));
    }

    public function test_buy_x_get_y_free_requires_the_same_menu_item_not_any_three(): void
    {
        $promotion = $this->makePromotion(['type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 3, 'free_quantity' => 1]);
        // 2 of item A + 1 of item B — neither reaches 3 units of the SAME item.
        $itemRows = [
            ['menu_item_id' => 1, 'unit_price_snapshot' => 1000, 'quantity' => 2],
            ['menu_item_id' => 2, 'unit_price_snapshot' => 1500, 'quantity' => 1],
        ];

        $this->assertSame(0, $this->service->calculateDiscount($promotion, 3500, $itemRows));
    }

    public function test_buy_x_get_y_free_sums_quantity_across_separate_lines_of_the_same_item(): void
    {
        $promotion = $this->makePromotion(['type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 3, 'free_quantity' => 1]);
        // Same menu item split across two cart lines (e.g. different notes/options) — 2 + 1 = 3 units.
        $itemRows = [
            ['menu_item_id' => 1, 'unit_price_snapshot' => 1000, 'quantity' => 2],
            ['menu_item_id' => 1, 'unit_price_snapshot' => 1000, 'quantity' => 1],
        ];

        $this->assertSame(1000, $this->service->calculateDiscount($promotion, 3000, $itemRows));
    }

    public function test_findActiveAutomatic_returns_a_promotion_matching_today(): void
    {
        $today = now('Africa/Accra')->dayOfWeek;
        $this->makePromotion(['is_automatic' => true, 'recurring_days' => [$today]]);

        $result = $this->service->findActiveAutomatic($this->branch);

        $this->assertNotNull($result);
    }

    public function test_findActiveAutomatic_ignores_a_promotion_not_recurring_today(): void
    {
        $today = now('Africa/Accra')->dayOfWeek;
        $otherDay = ($today + 1) % 7;
        $this->makePromotion(['is_automatic' => true, 'recurring_days' => [$otherDay]]);

        $this->assertNull($this->service->findActiveAutomatic($this->branch));
    }

    public function test_findActiveAutomatic_ignores_a_non_automatic_promotion(): void
    {
        $today = now('Africa/Accra')->dayOfWeek;
        $this->makePromotion(['is_automatic' => false, 'recurring_days' => [$today]]);

        $this->assertNull($this->service->findActiveAutomatic($this->branch));
    }

    public function test_findActiveAutomatic_excludes_bolt_food(): void
    {
        $today = now('Africa/Accra')->dayOfWeek;
        $this->makePromotion(['is_automatic' => true, 'recurring_days' => [$today]]);

        $this->assertNull($this->service->findActiveAutomatic($this->branch, 'bolt_food'));
    }

    public function test_findActiveAutomatic_respects_branch_restriction(): void
    {
        $today = now('Africa/Accra')->dayOfWeek;
        $promotion = $this->makePromotion(['is_automatic' => true, 'recurring_days' => [$today]]);
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000003', 'address' => 'C',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $promotion->branches()->attach($otherBranch->id);

        $this->assertNull($this->service->findActiveAutomatic($this->branch));
    }

    public function test_findActiveAutomatic_with_no_branch_only_matches_storewide_promotions(): void
    {
        $today = now('Africa/Accra')->dayOfWeek;
        $promotion = $this->makePromotion(['is_automatic' => true, 'recurring_days' => [$today]]);
        $promotion->branches()->attach($this->branch->id);

        $this->assertNull($this->service->findActiveAutomatic(null));
    }

    public function test_an_automatic_promotions_own_code_cannot_be_applied_manually(): void
    {
        $today = now('Africa/Accra')->dayOfWeek;
        $this->makePromotion(['is_automatic' => true, 'recurring_days' => [$today]]);

        $this->expectException(OrderPlacementException::class);

        $this->service->validate('WELCOME10', $this->branch, $this->customer, 10000);
    }

    private function makeOrder(): Order
    {
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->status = 'paid';
        $order->save();

        return $order;
    }
}
