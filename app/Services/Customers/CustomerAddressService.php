<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryArea;
use App\Services\Orders\Data\DeliveryAddressData;
use Illuminate\Support\Facades\DB;

/**
 * A logged-in customer's saved delivery addresses (owner, 2026-11-11):
 * saved from checkout ("Save this address for next time") or added in My
 * account with the phone's current location. Picking one at checkout
 * fills the area, landmark, GhanaPost code and location in, so a
 * returning customer needn't share their location again. A customer's
 * first address is their default; deleting the default promotes the
 * newest remaining one.
 *
 * lat/lng stay the customer's own: staff never see them (schema.md's
 * Customers section) — only the order's snapshot reaches the rider.
 */
class CustomerAddressService
{
    /**
     * @param  array{label?: ?string, delivery_area_id?: ?int, area_other?: ?string, ghanapost_code?: ?string, landmark: string, lat?: ?float, lng?: ?float}  $data
     */
    public function save(Customer $customer, array $data, ?CustomerAddress $address = null): CustomerAddress
    {
        $attributes = [
            'label' => filled($data['label'] ?? null) ? trim($data['label']) : null,
            'delivery_area_id' => $data['delivery_area_id'] ?? null,
            'area_other' => empty($data['delivery_area_id']) && filled($data['area_other'] ?? null) ? trim($data['area_other']) : null,
            'ghanapost_code' => filled($data['ghanapost_code'] ?? null) ? strtoupper(trim($data['ghanapost_code'])) : null,
            'landmark' => trim($data['landmark']),
        ];

        // Editing the text keeps the saved location unless a new one came in.
        if (array_key_exists('lat', $data) && $data['lat'] !== null) {
            $attributes['lat'] = $data['lat'];
            $attributes['lng'] = $data['lng'];
        }

        if ($address) {
            $address->update($attributes);

            return $address;
        }

        return $customer->addresses()->create([
            ...$attributes,
            'is_default' => ! $customer->addresses()->exists(),
        ]);
    }

    /**
     * The same landmark and area twice is the same place — refresh its
     * location instead of piling up duplicates.
     */
    public function saveFromCheckout(Customer $customer, DeliveryAddressData $delivery): CustomerAddress
    {
        $areaId = $delivery->areaId ?? ($delivery->areaOther ? DeliveryArea::where('name', $delivery->areaOther)->value('id') : null);

        $existing = $customer->addresses()
            ->where('landmark', trim($delivery->landmark))
            ->where(fn ($query) => $areaId ? $query->where('delivery_area_id', $areaId) : $query->whereNull('delivery_area_id'))
            ->first();

        return $this->save($customer, [
            'label' => $existing?->label,
            'delivery_area_id' => $areaId,
            'area_other' => $areaId ? null : $delivery->areaOther,
            'ghanapost_code' => $delivery->ghanapostCode,
            'landmark' => $delivery->landmark,
            'lat' => $delivery->lat,
            'lng' => $delivery->lng,
        ], $existing);
    }

    public function makeDefault(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            CustomerAddress::where('customer_id', $address->customer_id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
    }

    public function delete(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $customerId = $address->customer_id;
            $address->delete();

            if ($wasDefault) {
                CustomerAddress::where('customer_id', $customerId)->latest('id')->first()?->update(['is_default' => true]);
            }
        });
    }
}
