<?php

namespace App\Services\Orders\Data;

final class PlaceOrderItemData
{
    /**
     * @param  int[]  $optionIds
     */
    public function __construct(
        public readonly int $menuItemId,
        public readonly int $quantity,
        public readonly ?string $notes = null,
        public readonly array $optionIds = [],
    ) {}
}
