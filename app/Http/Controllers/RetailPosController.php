<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderPlacementException;
use App\Models\HeldSale;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Branches\BranchContext;
use App\Services\Cart\PosCartService;
use App\Services\Menu\MenuPricingService;
use App\Services\Orders\CounterReturnService;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Pos\HeldSaleService;
use App\Services\Pos\PharmacySaleService;
use App\Services\Pos\RetailPosService;
use App\Services\Promotions\PromotionService;
use App\Services\Shifts\ShiftService;
use App\Services\Stock\BatchService;
use App\Services\Users\PinService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The pharmacy and shop till (owner-approved 2026-11-27; plan step R3), on
 * the POS page beside PosController's checkout: search and scan, packs, a
 * price change or a return approved by a manager's Staff ID and PIN, and
 * held sales. Pharmacies and shops only (the `retail` route middleware);
 * whoever takes POS orders. The services do the work.
 */
class RetailPosController extends Controller
{
    public function search(Request $request, BranchContext $context, RetailPosService $pos): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        return response()->json(['products' => $pos->search($branch, (string) $request->query('q', ''))]);
    }

    /** A barcode or product code scanned (or typed and Enter): one of it into the sale. */
    public function scan(Request $request, BranchContext $context, RetailPosService $pos, PosCartService $cart, PromotionService $promotions): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:64']])['code'];

        $found = $pos->scan($branch, $code);
        app(BatchService::class)->assertSellable($found['item'], $branch->id);
        $cart->add($branch->id, $found['item']->id, 1, null, [], $found['pack']?->id);
        $payload = PosController::cartPayload($cart, $promotions);

        return response()->json([...$payload, 'added' => $found['item']->name.($found['pack'] ? ' ('.$found['pack']->name.')' : ''), 'stock_warning' => $pos->stockWarning($branch, $found['item']->id, $payload['lines'])]);
    }

    /** A product (or one of its packs) chosen from the search, with a quantity. */
    public function add(Request $request, BranchContext $context, PosCartService $cart, MenuPricingService $pricing, PromotionService $promotions, RetailPosService $pos): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $v = $request->validate([
            'menu_item_id' => ['required', 'integer'],
            'pack_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.PosCartService::maxLineQuantity()],
        ]);

        if ($product = MenuItem::find($v['menu_item_id'])) {
            app(BatchService::class)->assertSellable($product, $branch->id);
        }

        try {
            $pricing->priceItem($branch, new PlaceOrderItemData(menuItemId: $v['menu_item_id'], quantity: $v['quantity'], packId: $v['pack_id'] ?? null));
        } catch (OrderPlacementException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $cart->add($branch->id, $v['menu_item_id'], $v['quantity'], null, [], $v['pack_id'] ?? null);
        $payload = PosController::cartPayload($cart, $promotions);

        return response()->json([...$payload, 'stock_warning' => $pos->stockWarning($branch, $v['menu_item_id'], $payload['lines'])]);
    }

    /** A line's price changed (or put back), approved by a manager's Staff ID and PIN. */
    public function price(Request $request, string $line, BranchContext $context, PosCartService $cart, PinService $pins, PromotionService $promotions): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $v = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'staff_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits_between:4,6'],
        ], [
            'staff_code.required' => __('A manager types their Staff ID and PIN to approve this.'),
            'pin.required' => __('A manager types their Staff ID and PIN to approve this.'),
        ]);

        $approver = $pins->approver($v['staff_code'], $v['pin'], $branch->id);
        $cart->setPrice($line, isset($v['price']) ? Money::toPesewas($v['price']) : null, $approver->id);

        return response()->json(PosController::cartPayload($cart, $promotions));
    }

    /**
     * A pharmacy's restricted medicines approved by a manager, with the
     * prescription when one is needed (R5, PharmacySaleService).
     */
    public function pharmacyCheck(Request $request, BranchContext $context, PosCartService $cart, PinService $pins, PharmacySaleService $pharmacy, PromotionService $promotions): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($pharmacy->applies(), 404);
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $v = $request->validate([
            'staff_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits_between:4,6'],
            'prescription' => ['array'],
            'prescription.patient_name' => ['nullable', 'string', 'max:255'],
            'prescription.patient_phone' => ['nullable', 'string', 'max:30'],
            'prescription.prescriber_name' => ['nullable', 'string', 'max:255'],
            'prescription.prescriber_facility' => ['nullable', 'string', 'max:255'],
            'prescription.issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'prescription.prescription_number' => ['nullable', 'string', 'max:60'],
            'prescription.notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ], [
            'staff_code.required' => __('A manager types their Staff ID and PIN to approve this.'),
            'pin.required' => __('A manager types their Staff ID and PIN to approve this.'),
            'prescription.issued_on.before_or_equal' => __('The prescription date can\'t be in the future. Check the date on it.'),
            'photo.max' => __('The photo is bigger than 5 MB. Take it again at a lower size.'),
        ]);

        $approver = $pins->approver($v['staff_code'], $v['pin'], $branch->id);
        $pharmacy->approve($cart->summary()['lines'], $approver, $v['prescription'] ?? [], $request->file('photo'), $branch->id);

        return response()->json(PosController::cartPayload($cart, $promotions));
    }

    public function held(BranchContext $context, HeldSaleService $held): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        return response()->json(['held' => $held->forBranch($branch->id)->map(fn (HeldSale $sale) => [
            'id' => $sale->id,
            'label' => $sale->label,
            'by' => $sale->user?->name,
            'at' => $sale->created_at->timezone('Africa/Accra')->format('H:i'),
            'lines' => count($sale->cart['items'] ?? []),
        ])->values()]);
    }

    public function hold(Request $request, BranchContext $context, PosCartService $cart, HeldSaleService $held, PromotionService $promotions): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $held->hold($cart, $request->user(), $branch->id, $request->validate(['label' => ['nullable', 'string', 'max:60']])['label'] ?? null);

        return response()->json(PosController::cartPayload($cart, $promotions));
    }

    public function recall(HeldSale $heldSale, PosCartService $cart, HeldSaleService $held, PromotionService $promotions): JsonResponse
    {
        Gate::authorize('orders.create');

        $held->recall($heldSale, $cart);

        return response()->json(PosController::cartPayload($cart, $promotions));
    }

    public function discard(HeldSale $heldSale, HeldSaleService $held): JsonResponse
    {
        Gate::authorize('orders.create');

        $held->discard($heldSale);

        return response()->json(['ok' => true]);
    }

    /** A sale found by its receipt number, with what can still come back. */
    public function findSale(Request $request, BranchContext $context, CounterReturnService $returns): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless($branch = $context->branch(), 422, 'No branch selected.');

        $found = $returns->lookup((string) $request->validate(['reference' => ['required', 'string', 'max:40']])['reference'], $branch->id);

        return response()->json([
            'order' => ['id' => $found['order']->id, 'reference' => $found['order']->reference, 'payment_method' => $found['order']->payment_method, 'placed_at' => $found['order']->placed_at?->timezone('Africa/Accra')->format('j M Y, H:i')],
            'lines' => $found['lines'],
        ]);
    }

    public function return(Request $request, Order $order, BranchContext $context, CounterReturnService $returns, PinService $pins, ShiftService $shifts): JsonResponse
    {
        Gate::authorize('orders.create');
        abort_unless(($branch = $context->branch()) && $order->branch_id === $branch->id, 404);

        $v = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0'],
            'damaged' => ['array'],
            'damaged.*' => ['integer'],
            'reason' => ['required', 'string', 'max:255'],
            'staff_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits_between:4,6'],
        ], [
            'reason.required' => __('Say why it came back, e.g. Wrong size or Damaged.'),
            'staff_code.required' => __('A manager types their Staff ID and PIN to approve this.'),
            'pin.required' => __('A manager types their Staff ID and PIN to approve this.'),
        ]);

        $approver = $pins->approver($v['staff_code'], $v['pin'], $branch->id);
        $refund = $returns->create($order, array_map('intval', $v['quantities']), $approver, $v['reason'], $shifts->activeForBranch($branch->id)?->id, array_map('intval', $v['damaged'] ?? []));

        return response()->json([
            'amount' => $refund->amount,
            'method' => $order->payment_method,
            'message' => __('Return done: give back :amount (:method).', ['amount' => 'GH₵'.number_format($refund->amount / 100, 2), 'method' => $order->payment_method === 'cash' ? __('cash from the till') : __('by MoMo')]),
        ]);
    }
}
