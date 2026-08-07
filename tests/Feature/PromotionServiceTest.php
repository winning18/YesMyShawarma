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
