<?php

namespace App\Support;

use App\Services\Settings\SettingsService;

/**
 * Links meant for customers (owner, 2026-11-24): on the restaurant's own
 * website domain when it has one, otherwise wherever the app is. Use it
 * for every customer link made outside a customer's own page view —
 * SMS, tracking links shown to staff, structured data.
 */
class CustomerUrl
{
    /**
     * @param  array<string, mixed>|mixed  $parameters
     */
    public static function route(string $name, mixed $parameters = []): string
    {
        $domain = app(SettingsService::class)->websiteDomain();

        return $domain
            ? 'https://'.$domain.route($name, $parameters, false)
            : route($name, $parameters);
    }

    public static function home(): string
    {
        $domain = app(SettingsService::class)->websiteDomain();

        return $domain ? 'https://'.$domain : rtrim(url('/'), '/');
    }
}
