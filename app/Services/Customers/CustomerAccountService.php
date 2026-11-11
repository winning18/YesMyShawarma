<?php

namespace App\Services\Customers;

use App\Exceptions\CustomerAccountException;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use Illuminate\Support\Facades\DB;

/**
 * The customer's own "My account" changes (owner, 2026-11-11). Phone is
 * the login and the identity key (schema.md's Customers section), so
 * changing it only ever happens after SmsCodeService proved the customer
 * holds the new number — the controller verifies the code, this applies
 * it.
 *
 * If the new number already belongs to a guest record (orders placed
 * without an account), those orders, reviews, redemptions and addresses
 * move onto this account, each moved order logging an order_events row;
 * the guest row stays, with its phone cleared, so nothing pointing at it
 * breaks. A number held by another registered or suspended account is
 * refused.
 */
class CustomerAccountService
{
    public function __construct(private readonly CustomerService $customers) {}

    public function updateDetails(Customer $customer, string $name, ?string $email): Customer
    {
        $customer->update(['name' => $name, 'email' => $email ?: null]);

        return $customer;
    }

    public function changePassword(Customer $customer, string $password): void
    {
        $customer->update(['password' => $password]);
    }

    /**
     * Throws before a code is ever texted if the number can't be used.
     */
    public function assertPhoneAvailable(Customer $customer, string $phone): void
    {
        if ($customer->phone === $phone) {
            throw CustomerAccountException::samePhone();
        }

        $holder = Customer::where('phone', $phone)->first();

        if ($holder && ($holder->password !== null || $holder->suspended_at !== null)) {
            throw CustomerAccountException::phoneTaken();
        }
    }

    public function changePhone(Customer $customer, string $phone): Customer
    {
        $this->assertPhoneAvailable($customer, $phone);

        return DB::transaction(function () use ($customer, $phone) {
            $guest = Customer::where('phone', $phone)->lockForUpdate()->first();

            if ($guest) {
                $this->mergeGuest($guest, $customer);
            }

            $customer->update(['phone' => $phone, 'phone_verified_at' => now()]);

            return $customer;
        });
    }

    /**
     * On for broadcasts unless opted out — the same flag the SMS link
     * sets (SmsBroadcastService::optOut()). Order texts ignore it.
     */
    public function setSmsOffers(Customer $customer, bool $wanted): void
    {
        $customer->update(['marketing_opted_out_at' => $wanted ? null : ($customer->marketing_opted_out_at ?? now())]);
    }

    /**
     * "Forgot password": the code already proved they hold the number.
     */
    public function resetPassword(string $phone, string $password): Customer
    {
        $customer = Customer::where('phone', $phone)->whereNotNull('password')->first();

        if (! $customer) {
            throw CustomerAccountException::noAccount();
        }

        $customer->update(['password' => $password]);

        return $customer;
    }

    public function hasAccount(string $phone): bool
    {
        return Customer::where('phone', $phone)->whereNotNull('password')->exists();
    }

    private function mergeGuest(Customer $guest, Customer $account): void
    {
        Order::withoutGlobalScope(BranchScope::class)->where('customer_id', $guest->id)->get()
            ->each(function (Order $order) use ($guest, $account) {
                $order->update(['customer_id' => $account->id]);
                $order->events()->create([
                    'from_status' => $order->status,
                    'to_status' => $order->status,
                    'actor_type' => 'customer',
                    'actor_id' => $account->id,
                    'meta' => ['action' => 'customer_merged', 'from_customer_id' => $guest->id, 'to_customer_id' => $account->id],
                ]);
            });

        foreach (['reviews', 'promotion_redemptions'] as $table) {
            DB::table($table)->where('customer_id', $guest->id)->update(['customer_id' => $account->id]);
        }

        CustomerAddress::where('customer_id', $guest->id)->update(['customer_id' => $account->id, 'is_default' => false]);

        if (! $account->name && $guest->name) {
            $account->name = $guest->name;
        }

        $guest->update(['phone' => null]);
    }
}
