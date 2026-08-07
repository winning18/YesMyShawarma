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
     * @param  int[]  $optionIds
     */
    public function add(int $branchId, int $menuItemId, int $quantity, ?string $notes, array $optionIds): void
    {
        $cart = $this->raw();

        if ($cart['branch_id'] !== null && $cart['branch_id'] !== $branchId) {
            $cart = ['branch_id' => $branchId, 'items' => []];
        } else {
            $cart['branch_id'] = $branchId;
        }

        $optionIds = array_values($optionIds);
        $quantity = max(1, $quantity);

        // Same item, same notes, same exact set of options (order aside) —
        // bump the existing line's quantity instead of adding a second,
        // identical line the customer would have no reason to expect.
        foreach ($cart['items'] as &$item) {
            if ($this->isSameLine($item, $menuItemId, $notes, $optionIds)) {
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
            'option_ids' => $optionIds,
        ];

        $this->save($cart);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  int[]  $optionIds
     */
    private function isSameLine(array $item, int $menuItemId, ?string $notes, array $optionIds): bool
    {
        return $item['menu_item_id'] === $menuItemId
            && $item['notes'] === $notes
            && $this->sameOptionSet($item['option_ids'], $optionIds);
    }

    /**
     * @param  int[]  $a
     * @param  int[]  $b
     */
    private function sameOptionSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

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
            optionIds: $item['option_ids'],
        );
    }

    /**
     * @return array{branch_id: ?int, items: list<array<string, mixed>>}
     */
    private function raw(): array
    {
        return $this->request->session()->get($this->sessionKey(), ['branch_id' => null, 'items' => []]);
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
