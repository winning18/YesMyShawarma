<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Customers\CustomerService;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\OrderCreationService;
use App\Services\Payments\PaystackPaymentService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();

        return view('checkout.show', [
            ...$summary,
            'deliveryAvailable' => $deliveryAreas->isNotEmpty(),
            'deliveryAreas' => $deliveryAreas,
            'customer' => Auth::guard('customer')->user(),
            // For the client-side fee estimate shown the instant location
            // is captured — the authoritative fee is still always priced
            // server-side (OrderCreationService, or OrderStateMachine at
            // delivered if location wasn't captured at checkout).
            'ratePerKmPesewas' => DeliveryFeeCalculator::RATE_PER_KM_PESEWAS,
        ]);
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
        $deliveryAvailable = DeliveryArea::where('is_active', true)->exists();

        $rules = [
            'phone' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ];

        if ($deliveryAvailable) {
            $rules['fulfilment_type'] = ['required', 'in:pickup,delivery'];
            // "other" is the dropdown's own sentinel for "my area isn't
            // listed" — area_other carries the free-text name in that case,
            // and OrderCreationService turns it into a real DeliveryArea
            // row (so it's in the dropdown for the next customer too).
            $rules['area_id'] = [
                'required_if:fulfilment_type,delivery', 'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === 'other') {
                        return;
                    }
                    if (! DeliveryArea::where('id', $value)->where('is_active', true)->exists()) {
                        $fail('Please select a valid delivery area.');
                    }
                },
            ];
            $rules['area_other'] = ['required_if:area_id,other', 'nullable', 'string', 'max:100'];
            $rules['ghanapost_code'] = ['nullable', 'string'];
            $rules['landmark'] = ['required_if:fulfilment_type,delivery', 'nullable', 'string'];
            // Not required — the customer is strongly encouraged to share
            // it (see the checkout view), but geolocation can genuinely
            // fail (unsupported device, denied permission). When it's
            // present, OrderCreationService prices the fee immediately;
            // when it's not, the order still goes through with the fee
            // deferred to the "delivered" transition — see
            // OrderStateMachine and CheckoutController::show()'s note to
            // the customer about that.
            $rules['lat'] = ['nullable', 'numeric'];
            $rules['lng'] = ['nullable', 'numeric'];
        }

        $rules['payment_method'] = ['required', 'in:cash,paystack'];

        $validated = $request->validate($rules);

        $fulfilmentType = $deliveryAvailable ? $validated['fulfilment_type'] : 'pickup';

        $deliveryAddress = $fulfilmentType === 'delivery'
            ? new DeliveryAddressData(
                areaId: isset($validated['area_id']) && $validated['area_id'] !== 'other' ? (int) $validated['area_id'] : null,
                areaOther: $validated['area_id'] === 'other' ? $validated['area_other'] : null,
                ghanapostCode: $validated['ghanapost_code'] ?? null,
                landmark: $validated['landmark'],
                lat: isset($validated['lat']) ? (float) $validated['lat'] : null,
                lng: isset($validated['lng']) ? (float) $validated['lng'] : null,
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
                instructions: $validated['instructions'] ?? null,
            ));
        } catch (OrderPlacementException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        $cart->clear();

        if ($validated['payment_method'] === 'paystack') {
            // "The redirect page merely polls order status — it decides
            // nothing" (payments.md) — the tracking page already does
            // exactly that, so it doubles as the Paystack callback target.
            // The confirmation/thank-you page is cash-only: it states the
            // order is placed, which is only true immediately for cash
            // (paid at creation) — a Paystack order is still
            // pending_payment until the webhook fires.
            $result = $paystack->initializeForOrder($order, route('tracking.show', $order));

            return redirect()->away($result['authorization_url']);
        }

        return redirect()->route('checkout.confirmation', $order);
    }

    public function confirmation(Order $order): View
    {
        return view('checkout.confirmation', [
            'order' => $order->load(['items.options', 'branch', 'customer']),
        ]);
    }
}
