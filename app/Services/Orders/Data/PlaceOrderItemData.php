<?php

namespace App\Services\Orders\Data;

final class PlaceOrderItemData
{
    /**
     * @param  array<int, int>  $optionQuantities  option_id => quantity
     */
    public function __construct(
        public readonly int $menuItemId,
        public readonly int $quantity,
        public readonly ?string $notes = null,
        public readonly array $optionQuantities = [],
    ) {}
}
