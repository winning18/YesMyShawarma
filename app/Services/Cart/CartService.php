<?php

namespace App\Services\Cart;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Services\Menu\MenuPricingService;
use App\Services\Orders\Data\PlaceOrderItemData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Session-based — no login required for guest checkout. A cart belongs to
 * exactly one branch at a time (an order is placed from one branch), so
 * adding an item from a different branch starts a fresh cart rather than
 * mixing branches.
 */
class CartService
{
    public const MAX_LINE_QUANTITY = 20;

    public function __construct(
        private readonly Request $request,
        private readonly MenuPricingService $pricing,
    ) {}

    /**
     * @param  array<int, int>  $optionQuantities  option_id => quantity
     */
    public function add(int $branchId, int $menuItemId, int $quantity, ?string $notes, array $optionQuantities): void
    {
        $cart = $this->raw();

        if ($cart['branch_id'] !== null && $cart['branch_id'] !== $branchId) {
            $cart = ['branch_id' => $branchId, 'items' => []];
        } else {
            $cart['branch_id'] = $branchId;
        }

        $quantity = max(1, $quantity);

        // Same item, same notes, same exact options AND quantities — bump
        // the existing line's quantity instead of adding a second,
        // identical line the customer would have no reason to expect. A
        // different option quantity (e.g. this time with 2x cheese instead
        // of 1x) is a genuinely different line, not a merge candidate — see
        // MenuPricingService's "fixed total for the line" pricing.
        foreach ($cart['items'] as &$item) {
            if ($this->isSameLine($item, $menuItemId, $notes, $optionQuantities)) {
                $item['quantity'] = min(self::MAX_LINE_QUANTITY, $item['quantity'] + $quantity);
                $this->save($cart);

                return;
            }
        }
        unset($item);

        $cart['items'][] = [
            'id' => (string) Str::uuid(),
            'menu_item_id' => $menuItemId,
            'quantity' => min(self::MAX_LINE_QUANTITY, $quantity),
            'notes' => $notes,
            'options' => $optionQuantities,
        ];

        $this->save($cart);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, int>  $optionQuantities
     */
    private function isSameLine(array $item, int $menuItemId, ?string $notes, array $optionQuantities): bool
    {
        return $item['menu_item_id'] === $menuItemId
            && $item['notes'] === $notes
            && $this->sameOptions($item['options'], $optionQuantities);
    }

    /**
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    private function sameOptions(array $a, array $b): bool
    {
        ksort($a);
        ksort($b);

        return $a === $b;
    }

    public function updateQuantity(string $lineId, int $quantity): void
    {
        $cart = $this->raw();

        foreach ($cart['items'] as &$item) {
            if ($item['id'] === $lineId) {
                $item['quantity'] = max(1, min(self::MAX_LINE_QUANTITY, $quantity));
            }
        }

        $this->save($cart);
    }

    /**
     * Only ever adjusts an option already present on the line — the cart
     * page can tweak how many of an already-chosen extra it wants, but
     * can't add or remove which options are selected at all (that would
     * need re-validating group min/max/required, which this cheap,
     * single-field update deliberately doesn't do).
     */
    public function updateOptionQuantity(string $lineId, int $optionId, int $quantity): void
    {
        $cart = $this->raw();

        foreach ($cart['items'] as &$item) {
            if ($item['id'] === $lineId && array_key_exists($optionId, $item['options'])) {
                $item['options'][$optionId] = max(1, min(MenuPricingService::MAX_OPTION_QUANTITY, $quantity));
            }
        }

        $this->save($cart);
    }

    public function remove(string $lineId): void
    {
        $cart = $this->raw();
        $cart['items'] = array_values(array_filter($cart['items'], fn ($item) => $item['id'] !== $lineId));

        if (empty($cart['items'])) {
            $cart['branch_id'] = null;
        }

        $this->save($cart);
    }

    public function clear(): void
    {
        $this->request->session()->forget($this->sessionKey());
    }

    public function branchId(): ?int
    {
        return $this->raw()['branch_id'];
    }

    public function isEmpty(): bool
    {
        return empty($this->raw()['items']);
    }

    public function count(): int
    {
        return collect($this->raw()['items'])->sum('quantity');
    }

    /**
     * @return array{branch: ?Branch, lines: list<array<string, mixed>>, subtotal: int, dropped: string[]}
     */
    public function summary(): array
    {
        $cart = $this->raw();

        if (! $cart['branch_id'] || empty($cart['items'])) {
            return ['branch' => null, 'lines' => [], 'subtotal' => 0, 'dropped' => []];
        }

        $branch = Branch::find($cart['branch_id']);

        if (! $branch) {
            $this->clear();

            return ['branch' => null, 'lines' => [], 'subtotal' => 0, 'dropped' => []];
        }

        $lines = [];
        $dropped = [];
        $subtotal = 0;
        $stillValidItems = [];

        foreach ($cart['items'] as $item) {
            try {
                $priced = $this->pricing->priceItem($branch, $this->toItemData($item));
            } catch (OrderPlacementException $e) {
                $dropped[] = $e->getMessage();

                continue;
            }

            $priced['line_id'] = $item['id'];
            $lines[] = $priced;
            $subtotal += $priced['line_total'];
            $stillValidItems[] = $item;
        }

        if (count($stillValidItems) !== count($cart['items'])) {
            $cart['items'] = $stillValidItems;
            $this->save($cart);
        }

        return ['branch' => $branch, 'lines' => $lines, 'subtotal' => $subtotal, 'dropped' => $dropped];
    }

    /**
     * @return PlaceOrderItemData[]
     */
    public function toPlaceOrderItems(): array
    {
        return array_map(
            fn (array $item) => $this->toItemData($item),
            $this->raw()['items'],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toItemData(array $item): PlaceOrderItemData
    {
        return new PlaceOrderItemData(
            menuItemId: $item['menu_item_id'],
            quantity: $item['quantity'],
            notes: $item['notes'],
            optionQuantities: $item['options'],
        );
    }

    /**
     * @return array{branch_id: ?int, items: list<array<string, mixed>>}
     */
    private function raw(): array
    {
        $cart = $this->request->session()->get($this->sessionKey(), ['branch_id' => null, 'items' => []]);
        $cart['items'] = array_map($this->normalizeItem(...), $cart['items']);

        return $cart;
    }

    /**
     * A cart already sitting in a customer's session from before the
     * options shape changed from a flat option_ids list to an
     * option_id => quantity map carries the old key — normalise it here so
     * an in-progress session survives a deploy instead of crashing on its
     * next page load. Every option defaults to quantity 1, exactly what it
     * already meant under the old, quantity-less shape.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        if (! array_key_exists('options', $item) && array_key_exists('option_ids', $item)) {
            $item['options'] = array_fill_keys($item['option_ids'], 1);
            unset($item['option_ids']);
        }

        $item['options'] ??= [];

        return $item;
    }

    /**
     * @param  array{branch_id: ?int, items: list<array<string, mixed>>}  $cart
     */
    private function save(array $cart): void
    {
        $this->request->session()->put($this->sessionKey(), $cart);
    }

    /**
     * Overridden by PosCartService so a staff member's POS cart can never
     * collide with a guest customer's checkout cart in the same browser
     * session — both guards share one session store (see config/auth.php),
     * and this class's session key was previously a single shared constant.
     */
    protected function sessionKey(): string
    {
        return 'cart';
    }
}
