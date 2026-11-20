<?php

namespace App\Services\Settings;

use App\Models\Setting;

/**
 * A general-purpose key-value store for admin-editable business
 * configuration — the first use is the order reference prefixes
 * (OrderCreationService), reached via the "Settings" sidebar item
 * (SettingsController), but it's deliberately not named or shaped around
 * that one feature.
 */
class SettingsService
{
    public const ORDER_REFERENCE_PREFIX_POS = 'order_reference_prefix_pos';

    public const ORDER_REFERENCE_PREFIX_WEB = 'order_reference_prefix_web';

    /**
     * Bolt Food orders are POS orders (payment_method === 'bolt_food',
     * channel always 'pos' — payments.md) that otherwise got the plain POS
     * prefix, indistinguishable from a real in-house counter/phone order
     * at a glance. A separate prefix lets staff tell them apart on sight.
     */
    public const ORDER_REFERENCE_PREFIX_BOLT_FOOD = 'order_reference_prefix_bolt_food';

    /** WhatsApp group orders (orders.source 'whatsapp') — default YMGS-WA. */
    public const ORDER_REFERENCE_PREFIX_WHATSAPP = 'order_reference_prefix_whatsapp';

    /** Freebies (payment_method 'complimentary') — default YMGS-FREE. */
    public const ORDER_REFERENCE_PREFIX_FREEBIE = 'order_reference_prefix_freebie';

    /**
     * Pesewas off the food of a WhatsApp group order (owner, 2026-11-14;
     * GH₵10 to start). See OrderCreationService's WhatsApp reward.
     */
    public const WHATSAPP_REWARD_AMOUNT = 'whatsapp_reward_amount';

    /**
     * The home page's category strips as JSON, [{title, slugs[]}, …]
     * (HomeController::menuSliders()). Unset = one strip per category.
     */
    public const HOME_MENU_SLIDERS = 'home_menu_sliders';

    /**
     * Delivery fees, in pesewas (DeliveryFeeCalculator): per km from the
     * branch, the smallest fee charged, and the smallest order that can be
     * delivered. Unset = the calculator's starting values.
     */
    public const DELIVERY_RATE_PER_KM = 'delivery_rate_per_km';

    public const DELIVERY_MINIMUM_FEE = 'delivery_minimum_fee';

    public const DELIVERY_MINIMUM_ORDER = 'delivery_minimum_order';

    /**
     * Date (Y-m-d, Africa/Accra) from which riders hand their cash delivery
     * fees in (owner, 2026-11-11). Deliveries before it were the rider's to
     * keep and are never shown as owed. See RiderCashupService.
     */
    public const DELIVERY_FEE_HAND_IN_STARTS_ON = 'delivery_fee_hand_in_starts_on';

    /**
     * Delivery fees are handed in by riders and count as the business's
     * income ('handed_in', the default), or riders keep them as their pay
     * and they're kept out of every financial figure ('kept_by_rider').
     * Owner, 2026-11-20; see RiderCashupService.
     */
    public const DELIVERY_FEE_HANDLING = 'delivery_fee_handling';

    /**
     * From when riders hand the food money they collect at doors in
     * through rider cash-ups (before, it went into the till that took the
     * order). Unset on a new install: always.
     */
    public const RIDER_CASHUP_FOOD_STARTS_ON = 'rider_cashup_food_starts_on';

    /**
     * What each order reference prefix is before a business sets its own
     * (Express POS: neutral — Yes My Shawarma's YMGS-* values are stored
     * settings, copied in by the business profile migration).
     */
    public const DEFAULT_PREFIXES = [
        self::ORDER_REFERENCE_PREFIX_POS => 'POS',
        self::ORDER_REFERENCE_PREFIX_WEB => 'WEB',
        self::ORDER_REFERENCE_PREFIX_BOLT_FOOD => 'BOLT',
        self::ORDER_REFERENCE_PREFIX_WHATSAPP => 'WA',
        self::ORDER_REFERENCE_PREFIX_FREEBIE => 'FREE',
    ];

    public function prefix(string $key): string
    {
        return $this->get($key) ?: self::DEFAULT_PREFIXES[$key];
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return Setting::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Every setting in one query — for BusinessProfile, which reads a
     * dozen keys on every page.
     *
     * @return array<string, ?string>
     */
    public function all(): array
    {
        return Setting::pluck('value', 'key')->all();
    }

    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : $value === '1';
    }

    public function setBool(string $key, bool $value): void
    {
        $this->set($key, $value ? '1' : '0');
    }
}
