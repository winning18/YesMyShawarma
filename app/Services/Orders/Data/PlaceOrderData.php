<?php

namespace App\Services\Orders\Data;

use DateTimeInterface;

final class PlaceOrderData
{
    /**
     * @param  string  $fulfilmentType  'delivery' | 'pickup'
     * @param  string  $paymentMethod  'paystack' | 'cash'
     * @param  PlaceOrderItemData[]  $items
     */
    public function __construct(
        public readonly string $customerPhone,
        public readonly ?string $customerName,
        public readonly int $branchId,
        public readonly string $fulfilmentType,
        public readonly string $paymentMethod,
        public readonly array $items,
        public readonly ?DeliveryAddressData $deliveryAddress = null,
        public readonly ?DateTimeInterface $scheduledFor = null,
        public readonly ?string $instructions = null,
    ) {}
}
