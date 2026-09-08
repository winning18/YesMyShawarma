<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Services\Cart\CartService;
use App\Services\Menu\MenuPricingService;
use App\Services\Orders\Data\PlaceOrderItemData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(CartService $cart): View
    {
        return view('cart.show', $cart->summary());
    }

    public function add(Request $request, CartService $cart, MenuPricingService $pricing): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_LINE_QUANTITY],
            'notes' => ['nullable', 'string', 'max:255'],
            'option_ids' => ['array'],
            'option_ids.*' => ['integer'],
            // Only meaningful for an id also present in option_ids — a
            // quantity submitted for an option that isn't actually
            // selected is simply never read. Absent entirely for a
            // single-select group's option, which is always exactly one.
            'option_qty' => ['array'],
            'option_qty.*' => ['integer', 'min:1', 'max:'.MenuPricingService::MAX_OPTION_QUANTITY],
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);
        $optionQuantities = $this->optionQuantities($validated);

        try {
            $pricing->priceItem($branch, new PlaceOrderItemData(
                menuItemId: $validated['menu_item_id'],
                quantity: $validated['quantity'],
                notes: $validated['notes'] ?? null,
                optionQuantities: $optionQuantities,
            ));
        } catch (OrderPlacementException $e) {
            return back()->withErrors(['menu_item_id' => $e->getMessage()]);
        }

        $cart->add(
            $validated['branch_id'],
            $validated['menu_item_id'],
            $validated['quantity'],
            $validated['notes'] ?? null,
            $optionQuantities,
        );

        return back()->with('added_to_cart', true);
    }

    public function updateQuantity(Request $request, string $line, CartService $cart): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_LINE_QUANTITY]]);

        $cart->updateQuantity($line, $validated['quantity']);

        return back();
    }

    public function updateOptionQuantity(Request $request, string $line, int $option, CartService $cart): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.MenuPricingService::MAX_OPTION_QUANTITY],
        ]);

        $cart->updateOptionQuantity($line, $option, $validated['quantity']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>  option_id => quantity
     */
    private function optionQuantities(array $validated): array
    {
        $qty = $validated['option_qty'] ?? [];

        return collect($validated['option_ids'] ?? [])
            ->mapWithKeys(fn (int $id) => [$id => $qty[$id] ?? 1])
            ->all();
    }

    public function remove(string $line, CartService $cart): RedirectResponse
    {
        $cart->remove($line);

        return back();
    }
}
