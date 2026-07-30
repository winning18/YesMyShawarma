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
     * Assumes $phone already arrives normalised to E.164 — that's a
     * validation concern at the HTTP boundary, not this service's job.
     */
    public function findOrCreateByPhone(string $phone, ?string $name = null): Customer
    {
        $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $name]);

        if ($name && ! $customer->name) {
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
}
