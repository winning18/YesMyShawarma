<?php

namespace App\Services\Orders\Data;

final class DeliveryAddressData
{
    public function __construct(
        public readonly string $ghanapostCode,
        public readonly string $landmark,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
    ) {}
}
