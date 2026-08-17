<?php

namespace App\Services\Customers;

use App\Models\Customer;

class CustomerService
{
    /**
     * A guest row is created on first order whether or not the person ever
     * registers — registration later just sets a password on this same row,
     * so guest history carries over. See CLAUDE.md's identity model.
     *
     * $name always wins when provided and different — the checkout/
     * registration form field is an ordinary editable input (pre-filled
     * with whatever's on file, not read-only), so whatever was last typed
     * there is the name that should stick. Previously this only filled in
     * a name when the customer had none at all, which meant the very
     * first name ever entered for a phone number silently became
     * permanent — every later order kept showing it regardless of what
     * was typed at checkout, no matter how wrong or how long ago it was
     * a one-off placeholder. A null/empty $name (POS staff skipping the
     * field) leaves whatever's already on file untouched.
     *
     * Assumes $phone already arrives normalised to E.164 — that's a
     * validation concern at the HTTP boundary, not this service's job.
     */
    public function findOrCreateByPhone(string $phone, ?string $name = null): Customer
    {
        $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $name]);

        if ($name && $customer->name !== $name) {
            $customer->update(['name' => $name]);
        }

        return $customer;
    }

    /**
     * Ghana-specific E.164 normalisation for whatever a customer types at
     * the HTTP boundary (login/register forms) — "0243635265",
     * "233243635265", or "+233243635265" all resolve to the same value.
     */
    public function normalizeGhanaPhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($digits, '233')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+233'.substr($digits, 1);
        }

        return '+233'.$digits;
    }

    /**
     * Mirrors the client-side phoneField() rule (phone-input-script.blade.php)
     * at the HTTP boundary it was always meant to be enforced at: exactly 10
     * local digits ("0241234567", dashes stripped), or the same number
     * already given in 233/+233-prefixed form.
     */
    public function isValidGhanaPhone(string $raw): bool
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($digits, '233')) {
            return strlen($digits) === 12;
        }

        return strlen($digits) === 10 && str_starts_with($digits, '0');
    }
}
