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
     * Percentage/fixed only — buy_x_get_y_free never reduces a price, it
     * grants an extra unit instead (see appendFreeLines()), so it has
     * nothing to compute here; orders.discount_total stays 0 for that
     * type (OrderCreationService never calls this for it). Capped at
     * subtotal — never a negative total. See orders.md's totals section.
     */
    public function calculateDiscount(Promotion $promotion, int $subtotal): int
    {
        $discount = $promotion->type === 'percentage'
            ? (int) round($subtotal * $promotion->value / 100)
            : $promotion->value;

        return min($discount, $subtotal);
    }

    /**
     * How many free units a buy_x_get_y_free promotion grants for however
     * many of the same item were actually paid for — buy_quantity IS the
     * number paid per grant (e.g. "buy 2 get 1 free" is buy_quantity=2,
     * free_quantity=1), not a total group size, and it repeats: paying
     * for 4 (two full groups of 2) grants 2 free, not 1.
     */
    public function freeUnitsForQuantity(Promotion $promotion, int $paidQuantity): int
    {
        if ($promotion->type !== 'buy_x_get_y_free' || $promotion->buy_quantity < 1) {
            return 0;
        }

        return intdiv($paidQuantity, $promotion->buy_quantity) * $promotion->free_quantity;
    }

    /**
     * Returns $rows (MenuPricingService::priceItems()'s own row shape)
     * with one synthetic row appended per menu item that has earned a
     * free unit — never mutates an existing row. Grouped by menu_item_id,
     * not by cart line, since the same item can be split across several
     * lines with different options/notes and they all count toward the
     * same free-unit tally. The synthetic row is priced at 0 (not the
     * item's real unit_price_snapshot) and carries no options — a free
     * unit's own chosen options are still billed in full on whichever
     * paid line earned it, exactly as confirmed for this promo; the free
     * row itself is always the plain base item. Used identically by
     * CartService::summary() (so the free line shows up the moment it's
     * earned, before any order exists) and OrderCreationService::create()
     * (so the same row becomes a real, separate order_items row, not
     * folded into the paid one — see is_free on that table).
     *
     * @param  list<array{menu_item_id: int, name_snapshot: string, unit_price_snapshot: int, quantity: int}>  $rows
     * @return list<array<string, mixed>>
     */
    public function appendFreeLines(?Promotion $promotion, array $rows): array
    {
        if (! $promotion || $promotion->type !== 'buy_x_get_y_free') {
            return $rows;
        }

        $freeRows = collect($rows)
            ->groupBy('menu_item_id')
            ->map(function ($group) use ($promotion) {
                $freeUnits = $this->freeUnitsForQuantity($promotion, $group->sum('quantity'));

                if ($freeUnits < 1) {
                    return null;
                }

                $first = $group->first();

                return [
                    'menu_item_id' => $first['menu_item_id'],
                    'name_snapshot' => $first['name_snapshot'],
                    'unit_price_snapshot' => 0,
                    'quantity' => $freeUnits,
                    'line_total' => 0,
                    'notes' => null,
                    'options' => [],
                    'is_free' => true,
                    'line_id' => 'free-'.$first['menu_item_id'],
                    'image_url' => $first['image_url'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [...$rows, ...$freeRows];
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
