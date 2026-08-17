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

    public function get(string $key, ?string $default = null): ?string
    {
        return Setting::where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
