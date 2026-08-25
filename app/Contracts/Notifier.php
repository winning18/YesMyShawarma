<?php

namespace App\Contracts;

/**
 * SMS provider is Arkesel (see config/services.php) — everything that needs
 * to reach a human by SMS, staff escalation and customer order updates
 * alike, depends on this contract instead of a concrete provider, so
 * swapping providers later touches one binding, not every call site.
 *
 * Takes a phone number rather than a User/Customer model on purpose:
 * nothing here needs anything else off either model, and phone is the one
 * thing they'd otherwise have in common — typing this to a model would mean
 * conflating User and Customer, which permissions.md rules out entirely.
 */
interface Notifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(string $phone, string $message, array $context = []): void;
}
