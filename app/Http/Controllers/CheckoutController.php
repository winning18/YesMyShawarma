<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Customers\CustomerService;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Branches\WorkingHoursService;
use App\Services\Orders\OrderCreationService;
use App\Services\Payments\PaystackPaymentService;
use App\Services\Promotions\PromotionService;
use App\Services\Visitors\VisitorSessionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(CartService $cart, WorkingHoursService $workingHours): View|RedirectResponse
    {
        $summary = $cart->summary();

        if (! $summary['branch'] || empty($summary['lines'])) {
            return redirect()->route('cart.show')->with('status', 'Your cart is empty.');
        }

        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();
        $branchOpen = $workingHours->isOpenNow($summary['branch']);

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
            // Placing an order while closed is still allowed (see
            // OrderCreationService) — this just lets the page warn the
            // customer up front instead of them finding out only after.
            'branchOpen' => $branchOpen,
            'nextOpeningLabel' => $branchOpen ? null : $workingHours->nextOpening($summary['branch'])?->format('l g:ia'),
        ]);
    }

    public function store(
        Request $request,
        CartService $cart,
        CustomerService $customers,
        OrderCreationService $orders,
        PaystackPaymentService $paystack,
        VisitorSessionService $visitors,
    ): RedirectResponse {
        $summary = $cart->summary();

        if (! $summary['branch'] || empty($summary['lines'])) {
            return redirect()->route('cart.show')->with('status', 'Your cart is empty.');
        }

        $branch = $summary['branch'];
        $deliveryAvailable = DeliveryArea::where('is_active', true)->exists();

        $rules = [
            'phone' => [
                'required', 'string',
                function (string $attribute, mixed $value, Closure $fail) use ($customers): void {
                    if (! $customers->isValidGhanaPhone($value)) {
                        $fail('Please enter a valid Ghana phone number.');
                    }
                },
            ],
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
        $rules['promo_code'] = ['nullable', 'string'];

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
                promoCode: $validated['promo_code'] ?? null,
            ));
        } catch (OrderPlacementException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        $visitors->markConverted($request, $order);

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

    /**
     * Live check for the "Apply" button — never the final word. The
     * authoritative check happens again in OrderCreationService at actual
     * placement, since a client-reported result can't be trusted (the cart
     * could change, the code could expire, between here and submit).
     */
    public function applyPromoCode(Request $request, CartService $cart, CustomerService $customers, PromotionService $promotions): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'phone' => ['nullable', 'string'],
        ]);

        $summary = $cart->summary();

        if (! $summary['branch']) {
            return response()->json(['message' => __('Your cart is empty.')], 422);
        }

        // Not yet a real Customer row until the order is actually placed
        // (see CustomerService::findOrCreateByPhone) — an unsaved instance
        // has no id, which correctly matches zero redemptions in
        // PromotionService's max_per_customer check.
        $customer = null;
        if (! empty($validated['phone'])) {
            $phone = $customers->normalizeGhanaPhone($validated['phone']);
            $customer = Customer::where('phone', $phone)->first();
        }
        $customer ??= new Customer;

        try {
            $promotion = $promotions->validate($validated['code'], $summary['branch'], $customer, $summary['subtotal']);
        } catch (OrderPlacementException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'discount' => $promotions->calculateDiscount($promotion, $summary['subtotal']),
        ]);
    }

    public function confirmation(Order $order, WorkingHoursService $workingHours): View
    {
        $order->load(['items.options', 'items.menuItem', 'branch', 'customer']);
        $branchWasClosed = ! $workingHours->isOpenAt($order->branch, $order->placed_at);

        return view('checkout.confirmation', [
            'order' => $order,
            'branchWasClosed' => $branchWasClosed,
            'nextOpeningLabel' => $branchWasClosed ? $workingHours->nextOpening($order->branch, $order->placed_at)?->format('l g:ia') : null,
        ]);
    }
}
