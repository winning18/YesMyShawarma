<?php

namespace App\Services\Orders\Data;

use DateTimeInterface;

final class PlaceOrderData
{
    /**
     * @param  string  $fulfilmentType  'delivery' | 'pickup'
     * @param  string  $paymentMethod  'paystack' | 'cash'
     * @param  PlaceOrderItemData[]  $items
     * @param  string  $channel  'web' | 'pos'
     * @param  ?string  $actorType  who placed it, for order_events — defaults to
     *                              'customer' in OrderCreationService when null.
     *                              A POS-entered order passes the staff member's
     *                              role (see BranchContext::primaryRoleFor()).
     * @param  ?int  $actorId  paired with $actorType — defaults to the resolved
     *                         Customer's id when null.
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
        public readonly ?string $promoCode = null,
        public readonly string $channel = 'web',
        public readonly ?string $actorType = null,
        public readonly ?int $actorId = null,
    ) {}
}
