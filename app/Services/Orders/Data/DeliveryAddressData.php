<?php

namespace App\Services\Orders\Data;

final class DeliveryAddressData
{
    /**
     * @param  ?int  $areaId  Which named area (App\Models\DeliveryArea) the
     *                        customer picked from the dropdown — a label
     *                        for the rider only, does not affect price.
     * @param  ?string  $areaOther  Free-text area name when the customer's
     *                              area wasn't in the dropdown. Mutually
     *                              exclusive with $areaId — OrderCreationService
     *                              turns this into a real DeliveryArea row
     *                              (firstOrCreate), so it's in the dropdown
     *                              for the next customer too.
     * @param  ?float  $lat  The actual pricing input when present: distance
     *                       from the branch × DeliveryFeeCalculator's rate,
     *                       priced immediately in OrderCreationService. Null
     *                       when geolocation couldn't be captured — the fee
     *                       then defers to the "delivered" transition (see
     *                       OrderStateMachine), rather than blocking the
     *                       order outright.
     */
    public function __construct(
        public readonly ?int $areaId,
        public readonly ?string $ghanapostCode,
        public readonly string $landmark,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $areaOther = null,
    ) {}
}
