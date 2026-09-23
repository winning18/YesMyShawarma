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
     * Web checkout's Paystack option — see payments.md's "Paystack on/off"
     * section. Off by default (missing row, not just a falsy stored value)
     * so a fresh install/environment starts cash-only until someone
     * deliberately opts in, same reasoning as the order-reference prefixes
     * defaulting to a sane value rather than needing to be seeded.
     */
    public const PAYSTACK_ENABLED = 'paystack_enabled';

    public function get(string $key, ?string $default = null): ?string
    {
        return Setting::where('key', $key)->value('value') ?? $default;
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
