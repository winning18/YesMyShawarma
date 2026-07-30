<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Category;
use App\Services\Cart\CartService;
use App\Services\Customers\CustomerService;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\OrderCreationService;
use App\Services\Payments\PaystackPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(CartService $cart): View|RedirectResponse
    {
        $summary = $cart->summary();

        if (! $summary['branch'] || empty($summary['lines'])) {
            return redirect()->route('cart.show')->with('status', 'Your cart is empty.');
        }

        return view('checkout.show', [
            ...$summary,
            'deliveryAvailable' => $summary['branch']->deliveryZones()->where('is_active', true)->exists(),
            'customer' => Auth::guard('customer')->user(),
            'availableDrinks' => $this->availableDrinks($summary['branch']),
        ]);
    }

    /**
     * A quick "add a drink?" upsell right at checkout — reuses the same
     * cart.add endpoint the menu page uses, which redirects back() to
     * whichever page submitted it, so this needs no dedicated route.
     */
    private function availableDrinks(Branch $branch): Collection
    {
        $drinksCategory = Category::where('slug', 'drinks')->first();

        if (! $drinksCategory) {
            return collect();
        }

        return $branch->menuItems()
            ->where('menu_items.category_id', $drinksCategory->id)
            ->where('menu_items.is_active', true)
            ->wherePivot('is_available', true)
            ->orderBy('menu_items.sort_order')
            ->get();
    }

    public function store(
        Request $request,
        CartService $cart,
        CustomerService $customers,
        OrderCreationService $orders,
        PaystackPaymentService $paystack,
    ): RedirectResponse {
        $summary = $cart->summary();

        if (! $summary['branch'] || empty($summary['lines'])) {
            return redirect()->route('cart.show')->with('status', 'Your cart is empty.');
        }

        $branch = $summary['branch'];
        $deliveryAvailable = $branch->deliveryZones()->where('is_active', true)->exists();

        $rules = [
            'phone' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'in:cash,paystack'],
        ];

        if ($deliveryAvailable) {
            $rules['fulfilment_type'] = ['required', 'in:pickup,delivery'];
            $rules['ghanapost_code'] = ['required_if:fulfilment_type,delivery', 'nullable', 'string'];
            $rules['landmark'] = ['required_if:fulfilment_type,delivery', 'nullable', 'string'];
            $rules['lat'] = ['required_if:fulfilment_type,delivery', 'nullable', 'numeric'];
            $rules['lng'] = ['required_if:fulfilment_type,delivery', 'nullable', 'numeric'];
        }

        $validated = $request->validate($rules);

        $fulfilmentType = $deliveryAvailable ? $validated['fulfilment_type'] : 'pickup';

        $deliveryAddress = $fulfilmentType === 'delivery'
            ? new DeliveryAddressData(
                ghanapostCode: $validated['ghanapost_code'],
                landmark: $validated['landmark'],
                lat: (float) $validated['lat'],
                lng: (float) $validated['lng'],
            )
            : null;

        try {
            $order = $orders->create(new PlaceOrderData(
                customerPhone: $customers->normalizeGhanaPhone($validated['phone']),
                customerName: $validated['name'],
                branchId: $branch->id,
                fulfilmentType: $fulfilmentType,
                paymentMethod: $validated['payment_method'],
                items: $cart->toPlaceOrderItems(),
                deliveryAddress: $deliveryAddress,
            ));
        } catch (OrderPlacementException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        $cart->clear();

        if ($validated['payment_method'] === 'paystack') {
            // "The redirect page merely polls order status — it decides
            // nothing" (payments.md) — the tracking page already does
            // exactly that, so it doubles as the Paystack callback target.
            $result = $paystack->initializeForOrder($order, route('tracking.show', $order));

            return redirect()->away($result['authorization_url']);
        }

        return redirect()->route('tracking.show', $order);
    }
}
