<?php

namespace App\Services\Orders\Data;

final class PlaceOrderItemData
{
    /**
     * @param  array<int, int>  $optionQuantities  option_id => quantity
     * @param  ?int  $packId  a pharmacy's or shop's pack size sold (R3)
     * @param  ?int  $unitPriceOverride  pesewas: a price changed at the till, with a manager's approval (R3)
     * @param  ?int  $priceChangedBy  the manager who approved it
     */
    public function __construct(
        public readonly int $menuItemId,
        public readonly int $quantity,
        public readonly ?string $notes = null,
        public readonly array $optionQuantities = [],
        public readonly ?int $packId = null,
        public readonly ?int $unitPriceOverride = null,
        public readonly ?int $priceChangedBy = null,
    ) {}
}
