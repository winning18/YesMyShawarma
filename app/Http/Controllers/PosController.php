<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Services\Branches\BranchContext;
use App\Services\Cart\PosCartService;
use App\Services\Customers\CustomerService;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Menu\MenuPricingService;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Shifts\ShiftService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Staff-entered counter/phone orders — the POS side of the dashboard's two
 * purposes (see the header toggle in orders/dashboard.blade.php). Cart
 * state lives in PosCartService (its own session key, isolated from a
 * customer's checkout cart), but order creation itself goes through the
 * exact same OrderCreationService the web checkout uses — see that class's
 * docblock. Always operates on the staff member's currently resolved
 * branch (BranchContext), never a client-supplied branch id.
 */
class PosController extends Controller
{
    public function index(Request $request, BranchContext $context, PosCartService $cart, ShiftService $shifts): View|RedirectResponse
    {
        Gate::authorize('orders.create');

        // POS is not an owner feature — the business overview is where
        // they land instead (see OrderDashboardController::index() for the
        // Orders-board equivalent of this same rule).
        if ($context->hasRoleAtAnyBranch($request->user(), 'owner')) {
            return redirect()->route('dashboard.performance');
        }

        $branch = $context->branch();

        // Owner is never forced through branch selection (ResolveCurrentBranch
        // treats a cross-branch view as their default) — but POS needs one
        // physical branch, so send them to pick one instead of dead-ending on
        // a bare 403 the way every other multi-branch role would never see.
        if (! $branch) {
            // guest() remembers this URL so BranchSelectionController::store()
            // sends the owner straight back to POS, not to Dashboard.
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to use POS.'));
        }

        // Mirrors MenuController::index()'s category/item shaping — POS
        // needs the same "what can actually be sold here right now" view,
        // just rendered for staff instead of a customer.
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'category' => $category,
                'items' => $branch->menuItems()
                    ->where('menu_items.category_id', $category->id)
                    ->where('menu_items.is_active', true)
                    ->wherePivot('is_available', true)
                    ->with(['optionGroups.options' => fn ($query) => $query->where('is_active', true)])
                    ->orderBy('menu_items.sort_order')
                    ->get(),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();

        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();

        $user = $request->user();
        $isStaff = $context->primaryRoleFor($user, $branch->id) === 'staff';

        // See OrderDashboardController::index()'s identical check (same
        // `branch_just_confirmed` loop-prevention flash — without it, a
        // multi-branch staff member reaching POS for the first time, with
        // no branch resolved at all yet, would bounce: ResolveCurrentBranch
        // sends them to pick one (storing POS itself as intended), picking
        // returns them here via that same intended() call, and this check
        // would immediately redirect them straight back to the picker
        // again, forever, since nothing ever marks the branch as freshly
        // confirmed).
        if ($isStaff && ! $shifts->activeForBranch($branch->id) && $context->branchIdsFor($user)->count() > 1 && ! $request->session()->get('branch_just_confirmed')) {
            return redirect()->route('branches.select');
        }

        return view('pos.index', [
            'branch' => $branch,
            'categories' => $categories,
            'deliveryAreas' => $deliveryAreas,
            'deliveryAvailable' => $deliveryAreas->isNotEmpty(),
            'ratePerKmPesewas' => DeliveryFeeCalculator::RATE_PER_KM_PESEWAS,
            'cart' => $this->cartPayload($cart),
            'isStaff' => $isStaff,
            'forceShiftStart' => $isStaff && ! $shifts->activeForBranch($branch->id),
            // Manager reaches this page via the dedicated Orders nav item
            // (dashboard.orders.live), never route('dashboard') — that now
            // redirects manager to the business overview instead.
            'ordersUrl' => $isStaff ? route('dashboard') : route('dashboard.orders.live'),
        ]);
    }

    public function addItem(Request $request, PosCartService $cart, MenuPricingService $pricing, BranchContext $context): JsonResponse
    {
        Gate::authorize('orders.create');

        $branch = $context->branch();
        abort_if(! $branch, 422, 'No branch selected.');

        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.PosCartService::MAX_LINE_QUANTITY],
            'notes' => ['nullable', 'string', 'max:255'],
            'option_ids' => ['array'],
            'option_ids.*' => ['integer'],
            // See CartController::add()'s equivalent field.
            'option_qty' => ['array'],
            'option_qty.*' => ['integer', 'min:1', 'max:'.MenuPricingService::MAX_OPTION_QUANTITY],
        ]);

        $optionQuantities = $this->optionQuantities($validated);

        try {
            $pricing->priceItem($branch, new PlaceOrderItemData(
                menuItemId: $validated['menu_item_id'],
                quantity: $validated['quantity'],
                notes: $validated['notes'] ?? null,
                optionQuantities: $optionQuantities,
            ));
        } catch (OrderPlacementException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $cart->add(
            $branch->id,
            $validated['menu_item_id'],
            $validated['quantity'],
            $validated['notes'] ?? null,
            $optionQuantities,
        );

        return response()->json($this->cartPayload($cart));
    }

    public function updateQuantity(Request $request, string $line, PosCartService $cart): JsonResponse
    {
        Gate::authorize('orders.create');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.PosCartService::MAX_LINE_QUANTITY],
        ]);

        $cart->updateQuantity($line, $validated['quantity']);

        return response()->json($this->cartPayload($cart));
    }

    public function updateOptionQuantity(Request $request, string $line, int $option, PosCartService $cart): JsonResponse
    {
        Gate::authorize('orders.create');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.MenuPricingService::MAX_OPTION_QUANTITY],
        ]);

        $cart->updateOptionQuantity($line, $option, $validated['quantity']);

        return response()->json($this->cartPayload($cart));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int> option_id => quantity
     */
    private function optionQuantities(array $validated): array
    {
        $qty = $validated['option_qty'] ?? [];

        return collect($validated['option_ids'] ?? [])
            ->mapWithKeys(fn (int $id) => [$id => $qty[$id] ?? 1])
            ->all();
    }

    public function removeItem(string $line, PosCartService $cart): JsonResponse
    {
        Gate::authorize('orders.create');

        $cart->remove($line);

        return response()->json($this->cartPayload($cart));
    }

    public function store(
        Request $request,
        PosCartService $cart,
        CustomerService $customers,
        OrderCreationService $orders,
        BranchContext $context,
    ): JsonResponse {
        Gate::authorize('orders.create');

        $summary = $cart->summary();

        if (! $summary['branch'] || empty($summary['lines'])) {
            return response()->json(['message' => __('The cart is empty.')], 422);
        }

        $branch = $summary['branch'];
        $deliveryAvailable = DeliveryArea::where('is_active', true)->exists();

        $rules = [
            'phone' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'fulfilment_type' => [
                'required', 'in:pickup,delivery',
                // Bolt's own courier handles delivery for a Bolt Food
                // order — never ours, so there's nothing for our rider
                // flow to do with one placed as 'delivery'.
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    if ($request->input('payment_method') === 'bolt_food' && $value !== 'pickup') {
                        $fail(__('Bolt Food orders must be pickup — Bolt handles delivery themselves.'));
                    }
                },
            ],
            'payment_method' => ['required', 'in:cash,momo,bolt_food'],
            // Momo only, and optional even then — staff may skip it during a
            // rush and enter it later via orders.confirm_momo_payment. The
            // frontend only ever shows this field for momo, but validation
            // doesn't need to enforce that: OrderCreationService ignores it
            // for any other payment_method (see PlaceOrderData docblock).
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ];

        if ($deliveryAvailable) {
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
            $rules['lat'] = ['nullable', 'numeric'];
            $rules['lng'] = ['nullable', 'numeric'];
        }

        $validated = $request->validate($rules);

        $deliveryAddress = $validated['fulfilment_type'] === 'delivery'
            ? new DeliveryAddressData(
                areaId: isset($validated['area_id']) && $validated['area_id'] !== 'other' ? (int) $validated['area_id'] : null,
                areaOther: ($validated['area_id'] ?? null) === 'other' ? $validated['area_other'] : null,
                ghanapostCode: $validated['ghanapost_code'] ?? null,
                landmark: $validated['landmark'],
                lat: isset($validated['lat']) ? (float) $validated['lat'] : null,
                lng: isset($validated['lng']) ? (float) $validated['lng'] : null,
            )
            : null;

        $user = $request->user();

        try {
            $order = $orders->create(new PlaceOrderData(
                customerPhone: ! empty($validated['phone']) ? $customers->normalizeGhanaPhone($validated['phone']) : null,
                customerName: $validated['name'] ?? null,
                branchId: $branch->id,
                fulfilmentType: $validated['fulfilment_type'],
                paymentMethod: $validated['payment_method'],
                items: $cart->toPlaceOrderItems(),
                deliveryAddress: $deliveryAddress,
                instructions: $validated['instructions'] ?? null,
                channel: 'pos',
                actorType: $context->primaryRoleFor($user, $branch->id),
                actorId: $user->id,
                paymentReference: $validated['payment_reference'] ?? null,
            ));
        } catch (OrderPlacementException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $cart->clear();

        return response()->json([
            'order' => [
                'reference' => $order->reference,
                'total' => $order->total,
                'tracking_url' => route('tracking.show', $order),
            ],
        ]);
    }

    /**
     * @return array{branch: ?array<string, mixed>, lines: list<array<string, mixed>>, subtotal: int, dropped: string[]}
     */
    private function cartPayload(PosCartService $cart): array
    {
        $summary = $cart->summary();

        return [
            'branch' => $summary['branch'] ? ['id' => $summary['branch']->id, 'name' => $summary['branch']->name] : null,
            'lines' => $summary['lines'],
            'subtotal' => $summary['subtotal'],
            'dropped' => $summary['dropped'],
        ];
    }
}
