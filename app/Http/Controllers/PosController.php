<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\Category;
use App\Models\ComplimentaryOrder;
use App\Models\DeliveryArea;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Cart\PosCartService;
use App\Services\Customers\CustomerService;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Menu\MenuPricingService;
use App\Services\Modules\ModuleService;
use App\Services\Orders\Data\ComplimentaryData;
use App\Services\Orders\Data\DeliveryAddressData;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Printing\PrintJobService;
use App\Services\Promotions\PromotionService;
use App\Services\Settings\BusinessType;
use App\Services\Settings\SettingsService;
use App\Services\Shifts\CounterService;
use App\Services\Shifts\ShiftService;
use App\Services\Tables\TableTabService;
use App\Services\Tax\TaxService;
use App\Support\CustomerUrl;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
    public function index(Request $request, BranchContext $context, PosCartService $cart, ShiftService $shifts, PromotionService $promotions): View|RedirectResponse
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

        // No delivery at all while Delivery and riders is switched off (ModuleService).
        $deliveryAreas = app(ModuleService::class)->enabled(ModuleService::DELIVERY) ? DeliveryArea::where('is_active', true)->orderBy('name')->get() : collect();

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
        if ($isStaff && ! $shifts->activeForBranch($branch->id) && app(CounterService::class)->canTakeSalesHere($branch->id) && $context->branchIdsFor($user)->count() > 1 && ! $request->session()->get('branch_just_confirmed')) {
            return redirect()->route('branches.select');
        }

        // A branch with counters takes POS sales only on one of them
        // (CounterService, Express POS step 7a).
        if (! app(CounterService::class)->canTakeSalesHere($branch->id)) {
            return view('pos.not-a-counter', [
                'branch' => $branch,
                'canSetUp' => $user->can('settings.manage'),
            ]);
        }

        return view('pos.index', [
            'branch' => $branch,
            'categories' => $categories,
            'deliveryAreas' => $deliveryAreas,
            'deliveryAvailable' => $deliveryAreas->isNotEmpty(),
            'ratePerKmPesewas' => app(DeliveryFeeCalculator::class)->ratePerKm(),
            'cart' => $this->cartPayload($cart, $promotions),
            'posMode' => app(SettingsService::class)->posMode(),
            'checksMomo' => app(SettingsService::class)->checksMomoTransactionId(),
            'taxConfig' => app(TaxService::class)->clientConfig(),
            'whatsappReward' => (int) app(SettingsService::class)->get(SettingsService::WHATSAPP_REWARD_AMOUNT, '1000'),
            'freebieStaff' => $this->staffForFreebies(app(BranchContext::class)),
            'isStaff' => $isStaff,
            'forceShiftStart' => $isStaff && ! $shifts->activeForBranch($branch->id),
            // Manager reaches this page via the dedicated Orders nav item
            // (dashboard.orders.live), never route('dashboard') — that now
            // redirects manager to the business overview instead.
            'ordersUrl' => $isStaff ? route('dashboard') : route('dashboard.orders.live'),
        ]);
    }

    public function addItem(Request $request, PosCartService $cart, MenuPricingService $pricing, BranchContext $context, PromotionService $promotions): JsonResponse
    {
        $cart->forTable(request()->integer('table_id'));
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

        return response()->json($this->cartPayload($cart, $promotions));
    }

    public function updateQuantity(Request $request, string $line, PosCartService $cart, PromotionService $promotions): JsonResponse
    {
        $cart->forTable(request()->integer('table_id'));
        Gate::authorize('orders.create');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.PosCartService::MAX_LINE_QUANTITY],
        ]);

        $cart->updateQuantity($line, $validated['quantity']);

        return response()->json($this->cartPayload($cart, $promotions));
    }

    public function updateOptionQuantity(Request $request, string $line, int $option, PosCartService $cart, PromotionService $promotions): JsonResponse
    {
        $cart->forTable(request()->integer('table_id'));
        Gate::authorize('orders.create');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.MenuPricingService::MAX_OPTION_QUANTITY],
        ]);

        $cart->updateOptionQuantity($line, $option, $validated['quantity']);

        return response()->json($this->cartPayload($cart, $promotions));
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

    public function removeItem(string $line, PosCartService $cart, PromotionService $promotions): JsonResponse
    {
        $cart->forTable(request()->integer('table_id'));
        Gate::authorize('orders.create');

        $cart->remove($line);

        return response()->json($this->cartPayload($cart, $promotions));
    }

    /** One cart's lines and totals: the quick-sale cart, or a table's (?table_id=). */
    public function cart(Request $request, PosCartService $cart, PromotionService $promotions): JsonResponse
    {
        Gate::authorize('orders.create');

        return response()->json($this->cartPayload($cart->forTable($request->integer('table_id')), $promotions));
    }

    public function store(
        Request $request,
        PosCartService $cart,
        CustomerService $customers,
        OrderCreationService $orders,
        BranchContext $context,
        ModuleService $modules,
    ): JsonResponse {
        Gate::authorize('orders.create');

        // A table's round comes from that table's own items (2026-11-20).
        $cart->forTable($request->integer('table_id'));
        $summary = $cart->summary();

        if (! $summary['branch'] || empty($summary['lines'])) {
            return response()->json(['message' => __('The cart is empty.')], 422);
        }

        $branch = $summary['branch'];
        $deliveryAvailable = $modules->enabled(ModuleService::DELIVERY) && DeliveryArea::where('is_active', true)->exists();

        $rules = [
            'phone' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'fulfilment_type' => [
                // Dine in (owner, 2026-11-20): eating in, at a table or not.
                'required', Rule::in(array_filter(['pickup', app(BusinessType::class)->isRestaurant() ? 'dine_in' : null, $deliveryAvailable ? 'delivery' : null])),
                // Bolt's own courier handles delivery for a Bolt Food
                // order — never ours, so there's nothing for our rider
                // flow to do with one placed as 'delivery'.
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    if ($request->input('payment_method') === 'bolt_food' && $value !== 'pickup') {
                        $fail(__('Bolt Food orders must be pickup — Bolt handles delivery themselves.'));
                    }
                    // Freebies and WhatsApp group orders can be delivered (owner, 2026-11-20).
                },
            ],
            'payment_method' => ['required', Rule::in(array_keys(array_filter([
                'cash' => true,
                'momo' => true,
                Order::BOLT_FOOD => $modules->enabled(ModuleService::BOLT_FOOD),
                Order::COMPLIMENTARY => $modules->enabled(ModuleService::FREEBIES),
            ])))],
            // WhatsApp group: GH₵10 off the food, paid by cash or MoMo.
            'source' => [
                'nullable', Rule::in($modules->enabled(ModuleService::WHATSAPP_REWARD) ? [Order::SOURCE_WHATSAPP] : []),
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    if ($value === Order::SOURCE_WHATSAPP && ! in_array($request->input('payment_method'), ['cash', 'momo'], true)) {
                        $fail(__('WhatsApp group orders are paid by cash or MoMo.'));
                    }
                },
            ],
            // Freebies: who it's for and why (FreebieService).
            'freebie_recipient_type' => [Rule::requiredIf(fn () => $request->input('payment_method') === Order::COMPLIMENTARY), 'nullable', Rule::in([ComplimentaryOrder::RECIPIENT_CUSTOMER, ComplimentaryOrder::RECIPIENT_STAFF])],
            'freebie_recipient_user_id' => [
                Rule::requiredIf(fn () => $request->input('payment_method') === Order::COMPLIMENTARY && $request->input('freebie_recipient_type') === ComplimentaryOrder::RECIPIENT_STAFF),
                'nullable', 'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($context): void {
                    if ($value && ! $this->staffForFreebies($context)->contains('id', (int) $value)) {
                        $fail(__('Choose a staff member from this branch.'));
                    }
                },
            ],
            'freebie_recipient_name' => [
                Rule::requiredIf(fn () => $request->input('payment_method') === Order::COMPLIMENTARY && $request->input('freebie_recipient_type') === ComplimentaryOrder::RECIPIENT_CUSTOMER && ! filled($request->input('name')) && ! filled($request->input('phone'))),
                'nullable', 'string', 'max:255',
            ],
            'freebie_reason' => [Rule::requiredIf(fn () => $request->input('payment_method') === Order::COMPLIMENTARY), 'nullable', 'string', 'max:255'],
            // Momo only, and optional even then — staff may skip it during a
            // rush and enter it later via orders.confirm_momo_payment. The
            // frontend only ever shows this field for momo, but validation
            // doesn't need to enforce that: OrderCreationService ignores it
            // for any other payment_method (see PlaceOrderData docblock).
            'payment_reference' => ['nullable', 'string', 'max:255'],
            // Varies per order (owner): did the customer send the delivery
            // fee by MoMo with the food, or will they pay the rider cash?
            // Decides what the rider collects at the door.
            'delivery_fee_paid_by' => [
                Rule::requiredIf(fn () => in_array($request->input('payment_method'), ['momo', Order::COMPLIMENTARY], true) && $request->input('fulfilment_type') === 'delivery'),
                'nullable', Rule::in(Order::DELIVERY_FEE_PAID_BY),
            ],
            'instructions' => ['nullable', 'string', 'max:1000'],
            // Tables (2026-11-20): a round on a table's bill, paid when the bill is settled.
            'table_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($request, $modules, $branch): void {
                    if ($value === null) {
                        return;
                    }
                    if (! $modules->enabled(ModuleService::TABLES) || ! DiningTable::where('id', $value)->where('branch_id', $branch->id)->where('is_active', true)->exists()) {
                        $fail(__('Choose a table at this branch.'));
                    }
                    if ($request->input('fulfilment_type') === 'delivery' || ! in_array($request->input('payment_method'), ['cash', 'momo'], true) || $request->filled('source')) {
                        $fail(__('A table order is eat-in, paid by cash or MoMo when the bill is settled.'));
                    }
                },
            ],
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

        // Tables only (owner's setting): a cash or MoMo order belongs to a table.
        if (! isset($validated['table_id']) && in_array($validated['payment_method'], ['cash', 'momo'], true) && app(SettingsService::class)->posMode() === 'tables') {
            return response()->json(['message' => __('Choose a table for this order.'), 'errors' => ['table_id' => [__('Choose a table for this order.')]]], 422);
        }

        $user = $request->user();
        $tab = isset($validated['table_id'])
            ? app(TableTabService::class)->openTabFor(DiningTable::findOrFail($validated['table_id']), $user)
            : null;

        $customerPhone = ! empty($validated['phone']) ? $customers->normalizeGhanaPhone($validated['phone']) : null;
        $customerName = $validated['name'] ?? null;
        // A table keeps its customer (2026-11-20): new details are saved on its bill; none given, the bill's are used.
        if ($tab) {
            if ($customerPhone || filled($customerName)) {
                app(TableTabService::class)->setCustomer($tab, $customerName ?? $tab->customer_name, $customerPhone ?? $tab->customer_phone);
            }
            $customerPhone ??= $tab->customer_phone;
            $customerName = filled($customerName) ? $customerName : $tab->customer_name;
        }

        try {
            $order = $orders->create(new PlaceOrderData(
                customerPhone: $customerPhone,
                customerName: $customerName,
                branchId: $branch->id,
                // A table round is always dine in.
                fulfilmentType: $tab ? 'dine_in' : $validated['fulfilment_type'],
                paymentMethod: $validated['payment_method'],
                items: $cart->toPlaceOrderItems(),
                deliveryAddress: $deliveryAddress,
                instructions: $validated['instructions'] ?? null,
                channel: 'pos',
                actorType: $context->primaryRoleFor($user, $branch->id),
                actorId: $user->id,
                paymentReference: $validated['payment_reference'] ?? null,
                deliveryFeePaidBy: $validated['delivery_fee_paid_by'] ?? null,
                source: $validated['payment_method'] === Order::COMPLIMENTARY ? null : ($validated['source'] ?? null),
                complimentary: $validated['payment_method'] === Order::COMPLIMENTARY
                    ? new ComplimentaryData(
                        recipientType: $validated['freebie_recipient_type'],
                        recipientUserId: isset($validated['freebie_recipient_user_id']) ? (int) $validated['freebie_recipient_user_id'] : null,
                        recipientName: $validated['freebie_recipient_name'] ?? ($validated['name'] ?? null) ?: ($validated['phone'] ?? null),
                        reason: $validated['freebie_reason'],
                    )
                    : null,
                // A round: recorded as cash until the bill is settled (TableTabService::settle()).
                tableTabId: $tab?->id,
            ));
        } catch (OrderPlacementException $e) {
            // A bill opened just now for this round, with nothing on it, goes again.
            if ($tab && $tab->wasRecentlyCreated && ! $tab->orders()->exists()) {
                $tab->delete();
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $cart->clear();

        // Step 8b: receipt, kitchen ticket and cash drawer through the print
        // agent when it's on; otherwise the POS prints through the browser.
        $printedByAgent = app(PrintJobService::class)->forPosSale($order->fresh(['shift.terminal']), $request->user());

        return response()->json([
            'printed_by_agent' => $printedByAgent,
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'total' => $order->total,
                'table' => $tab?->table?->name,
                'tracking_url' => $modules->enabled(ModuleService::WEBSITE) ? CustomerUrl::route('tracking.show', $order) : null,
            ],
        ]);
    }

    /**
     * $discount (percentage/fixed only — buy_x_get_y_free's free line is
     * already part of $summary['lines'], nothing to subtract) and the
     * free line itself both assume a non-Bolt-Food payment method —
     * cart-building happens before payment_method is necessarily final
     * (it's a radio alongside the cart, not a separate step), so the live
     * preview can't know yet whether this will end up excluded.
     * pos/index.blade.php hides the free line and zeroes the discount
     * client-side the moment Bolt Food is selected; store()'s actual
     * OrderCreationService::create() call re-checks payment_method itself
     * regardless, so a wrong preview can never place a wrongly discounted
     * or wrongly free order.
     *
     * @return array{branch: ?array<string, mixed>, lines: list<array<string, mixed>>, subtotal: int, dropped: string[], discount: int, promotionBanner: ?string, foodSubtotal: int}
     */
    private function cartPayload(PosCartService $cart, PromotionService $promotions): array
    {
        $summary = $cart->summary();
        $promotion = $summary['branch'] ? $promotions->findActiveAutomatic($summary['branch']) : null;
        $discount = ($promotion && $promotion->type !== 'buy_x_get_y_free')
            ? $promotions->calculateDiscount($promotion, $summary['subtotal'])
            : 0;
        // For the WhatsApp group reward preview — mirrored client-side
        // exactly as OrderCreationService applies it.
        $foodSubtotal = $promotions->foodSubtotal($summary['lines']);

        return [
            'branch' => $summary['branch'] ? ['id' => $summary['branch']->id, 'name' => $summary['branch']->name] : null,
            'lines' => $summary['lines'],
            'subtotal' => $summary['subtotal'],
            'dropped' => $summary['dropped'],
            'discount' => $discount,
            'promotionBanner' => $promotion?->banner_headline,
            'foodSubtotal' => $foodSubtotal,
        ];
    }

    /**
     * Who a staff freebie can be for: everyone working at the current
     * branch (any role there), active accounts only.
     *
     * @return Collection<int, User>
     */
    private function staffForFreebies(BranchContext $context): Collection
    {
        $branchId = $context->id();

        if (! $branchId) {
            return collect();
        }

        return collect(['staff', 'manager', 'general_manager', 'rider', 'stock_manager'])
            ->flatMap(fn (string $role) => $context->usersWithRole($role, $branchId))
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
