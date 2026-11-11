<?php

namespace App\Services\Customers;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Menu\MenuPricingService;
use App\Services\Orders\Data\PlaceOrderItemData;

/**
 * "Order again" from My account: the same items, options, quantities and
 * notes back in the cart, at the branch the customer is ordering from now.
 * Anything that can no longer be ordered that way (sold out there, removed
 * from the menu, an option gone) is skipped and named. A promotion's free
 * line is never copied — the cart recomputes it.
 */
class ReorderService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly MenuPricingService $pricing,
    ) {}

    /**
     * @return array{added: int, skipped: list<string>}
     */
    public function reorder(Order $order, Branch $branch): array
    {
        $added = 0;
        $skipped = [];

        foreach ($order->items()->with('options')->where('is_free', false)->get() as $item) {
            $options = $item->options
                ->filter(fn ($option) => $option->option_id !== null)
                ->mapWithKeys(fn ($option) => [$option->option_id => max(1, (int) $option->quantity)])
                ->all();

            $data = new PlaceOrderItemData(
                menuItemId: (int) $item->menu_item_id,
                quantity: (int) $item->quantity,
                notes: $item->notes,
                optionQuantities: $options,
            );

            try {
                $this->pricing->priceItem($branch, $data);
            } catch (OrderPlacementException) {
                $skipped[] = $item->name_snapshot;

                continue;
            }

            $this->cart->add($branch->id, $data->menuItemId, $data->quantity, $data->notes, $options);
            $added++;
        }

        return ['added' => $added, 'skipped' => array_values(array_unique($skipped))];
    }
}
