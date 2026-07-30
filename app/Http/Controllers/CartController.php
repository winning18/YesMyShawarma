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
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);

        try {
            $pricing->priceItem($branch, new PlaceOrderItemData(
                menuItemId: $validated['menu_item_id'],
                quantity: $validated['quantity'],
                notes: $validated['notes'] ?? null,
                optionIds: $validated['option_ids'] ?? [],
            ));
        } catch (OrderPlacementException $e) {
            return back()->withErrors(['menu_item_id' => $e->getMessage()]);
        }

        $cart->add(
            $validated['branch_id'],
            $validated['menu_item_id'],
            $validated['quantity'],
            $validated['notes'] ?? null,
            $validated['option_ids'] ?? [],
        );

        return back()->with('status', 'Added to cart.');
    }

    public function updateQuantity(Request $request, string $line, CartService $cart): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_LINE_QUANTITY]]);

        $cart->updateQuantity($line, $validated['quantity']);

        return back();
    }

    public function remove(string $line, CartService $cart): RedirectResponse
    {
        $cart->remove($line);

        return back();
    }
}
