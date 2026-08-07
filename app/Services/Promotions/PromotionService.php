<?php

namespace App\Services\Promotions;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionRedemption;

class PromotionService
{
    /**
     * Every check that decides whether a code can be used at all, short of
     * actually spending it — callable from checkout's live "Apply" check
     * and from OrderCreationService's authoritative re-check alike, so the
     * two can never disagree about why a code was rejected.
     *
     * @throws OrderPlacementException
     */
    public function validate(string $code, Branch $branch, Customer $customer, int $subtotal): Promotion
    {
        $promotion = Promotion::where('code', $code)->with('branches')->first();

        if (! $promotion || ! $promotion->is_active) {
            throw OrderPlacementException::invalidPromoCode('This promo code is not valid.');
        }

        $now = now();

        if ($promotion->starts_at && $now->lt($promotion->starts_at)) {
            throw OrderPlacementException::invalidPromoCode('This promo code is not active yet.');
        }

        if ($promotion->ends_at && $now->gt($promotion->ends_at)) {
            throw OrderPlacementException::invalidPromoCode('This promo code has expired.');
        }

        // Empty = every branch — see Promotion::branches().
        if ($promotion->branches->isNotEmpty() && ! $promotion->branches->contains('id', $branch->id)) {
            throw OrderPlacementException::invalidPromoCode('This promo code is not valid at this branch.');
        }

        if ($promotion->min_order_total !== null && $subtotal < $promotion->min_order_total) {
            $cedis = number_format($promotion->min_order_total / 100, 2);

            throw OrderPlacementException::invalidPromoCode("This promo code requires a minimum order of GHS {$cedis}.");
        }

        if ($promotion->max_redemptions !== null
            && $promotion->redemptions()->count() >= $promotion->max_redemptions) {
            throw OrderPlacementException::invalidPromoCode('This promo code has reached its usage limit.');
        }

        if ($promotion->max_per_customer !== null
            && $promotion->redemptions()->where('customer_id', $customer->id)->count() >= $promotion->max_per_customer) {
            throw OrderPlacementException::invalidPromoCode('You have already used this promo code.');
        }

        return $promotion;
    }

    /**
     * Capped at subtotal — never a negative total. See orders.md's totals
     * section.
     */
    public function calculateDiscount(Promotion $promotion, int $subtotal): int
    {
        $discount = $promotion->type === 'percentage'
            ? (int) round($subtotal * $promotion->value / 100)
            : $promotion->value;

        return min($discount, $subtotal);
    }

    /**
     * Records that this specific order spent the code — the redemption
     * count max_redemptions/max_per_customer check in validate() against.
     * Called once, after the order it belongs to actually exists.
     */
    public function redeem(Promotion $promotion, Order $order, Customer $customer, int $amountDiscounted): void
    {
        PromotionRedemption::create([
            'promotion_id' => $promotion->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount_discounted' => $amountDiscounted,
        ]);
    }
}
