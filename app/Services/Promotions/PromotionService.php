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
     * The automatic, no-code equivalent of validate() — finds the one
     * recurring promotion (if any) that applies to this branch today, e.g.
     * "every Wednesday" (recurring_days). Never returns one for a Bolt Food
     * order: that payment method is deliberately excluded from every
     * automatic promotion, the same blanket rule Order::
     * EXCLUDED_FROM_SALES_PAYMENT_METHODS already applies to sales
     * reporting — Bolt Food orders are settled entirely on Bolt's own
     * platform, not something this promo should discount.
     *
     * At most one promotion is ever returned — orders.promotion_id only
     * ever holds a single promotion, so an automatic one always takes that
     * slot and coupon-code entry is hidden entirely on a day one applies
     * (enforced in the checkout/POS UI, not here).
     *
     * $paymentMethod is nullable: callers who don't know it yet (the
     * checkout page's initial render, the homepage banner — neither ever
     * means Bolt Food, which is POS-only) can still ask "is a promotion
     * live today" with no payment method at all; only an actual
     * 'bolt_food' value excludes one.
     *
     * $branch is nullable for the same reason as the homepage banner: a
     * first-time visitor who hasn't picked a branch yet can't be matched
     * against a branch-restricted promotion (promotion_branch), so only a
     * storewide one (empty pivot — applies to every branch) can ever be
     * returned with no branch given.
     */
    public function findActiveAutomatic(?Branch $branch, ?string $paymentMethod = null): ?Promotion
    {
        if ($paymentMethod === 'bolt_food') {
            return null;
        }

        $now = now();
        // Carbon's own numbering (0=Sunday..6=Saturday), matching
        // menu_item_schedules — Africa/Accra local, since "Wednesday" is a
        // local-calendar concept, not a UTC one.
        $today = now('Africa/Accra')->dayOfWeek;

        return Promotion::where('is_automatic', true)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->with('branches')
            ->get()
            ->first(fn (Promotion $promotion) => in_array($today, $promotion->recurring_days ?? [], true)
                && ($promotion->branches->isEmpty() || ($branch && $promotion->branches->contains('id', $branch->id))));
    }

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

        // Automatic promotions still need some unique code value for the
        // column's own constraint, but it's an internal label, never meant
        // to be typed in — findActiveAutomatic() is the only path that
        // should ever apply one, so this guards against that code value
        // being guessed or re-entered through the manual flow instead.
        if (! $promotion || ! $promotion->is_active || $promotion->is_automatic) {
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
     * section. $itemRows (MenuPricingService::priceItems()'s own row
     * shape) is only read for type === 'buy_x_get_y_free'; every other
     * type is still a plain subtotal-based calculation and ignores it.
     *
     * @param  list<array{menu_item_id: int, unit_price_snapshot: int, quantity: int}>  $itemRows
     */
    public function calculateDiscount(Promotion $promotion, int $subtotal, array $itemRows = []): int
    {
        if ($promotion->type === 'buy_x_get_y_free') {
            return min($this->calculateBuyXGetYFreeDiscount($promotion, $itemRows), $subtotal);
        }

        $discount = $promotion->type === 'percentage'
            ? (int) round($subtotal * $promotion->value / 100)
            : $promotion->value;

        return min($discount, $subtotal);
    }

    /**
     * Groups by menu_item_id (not by cart line — the same item can be
     * split across several lines with different options) and gives
     * free_quantity units for every buy_quantity units of that same item,
     * repeating per group rather than capping at one freebie per order.
     * Priced at unit_price_snapshot only — the item's base price, with no
     * option price_delta folded in (MenuPricingService never includes
     * options there) — so a free unit's own chosen options are still
     * billed in full, exactly as confirmed for this promo.
     *
     * @param  list<array{menu_item_id: int, unit_price_snapshot: int, quantity: int}>  $itemRows
     */
    private function calculateBuyXGetYFreeDiscount(Promotion $promotion, array $itemRows): int
    {
        return collect($itemRows)
            ->groupBy('menu_item_id')
            ->sum(function ($rows) use ($promotion) {
                $freeUnits = intdiv($rows->sum('quantity'), $promotion->buy_quantity) * $promotion->free_quantity;

                return $freeUnits * $rows->first()['unit_price_snapshot'];
            });
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
