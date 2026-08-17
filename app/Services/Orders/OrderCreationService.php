<?php

namespace App\Services\Orders;

use App\Events\OrderPlaced;
use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Services\Customers\CustomerService;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Menu\MenuPricingService;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Promotions\PromotionService;
use App\Services\Settings\SettingsService;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCreationService
{
    /**
     * Ambiguous characters (0/O, 1/I/L) excluded — same reasoning as
     * UserManagementService's temporary-password alphabet: this gets read
     * off a screen or a printed receipt and typed back in by hand (order
     * lookup, tracking).
     */
    private const REFERENCE_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly CustomerService $customers,
        private readonly MenuPricingService $pricing,
        private readonly DeliveryFeeCalculator $feeCalculator,
        private readonly PromotionService $promotions,
        private readonly SettingsService $settings,
    ) {}

    /**
     * The single order-creation entry point — guest web checkout and
     * staff-entered POS orders both call this, same as Web/Api controllers
     * alike (see CLAUDE.md's API-first rule). $data->channel and
     * $data->actorType/actorId are how a POS caller distinguishes itself;
     * everything else behaves identically regardless of caller.
     *
     * Deliberately NOT handled here, each for its own reason:
     * - the delivery_fee for delivery orders when location wasn't captured
     *   at checkout: distance (branch → the customer's shared live
     *   location) × a flat rate, priced immediately below when lat/lng are
     *   present, or deferred to the "delivered" transition — see
     *   OrderStateMachine::transition() — when geolocation couldn't be
     *   captured. Paystack is allowed for delivery in both cases: when
     *   location was captured, $order->total already includes the real fee
     *   by the time PaystackPaymentService charges it; when it wasn't,
     *   Paystack only charges the subtotal known at that point, and
     *   whatever the delivered-transition fee turns out to be needs
     *   collecting separately (cash to the rider) — there's no route to
     *   charge a Paystack transaction again after the fact.
     * - cross-branch routing by geocoordinate (schema.md's delivery_zones
     *   section): $data->branchId is assumed already resolved by the
     *   caller. delivery_areas is just a named-area label for the rider now,
     *   not a routing or pricing mechanism.
     * - branch_working_hours: outside opening hours does NOT block
     *   placement — see WorkingHoursService::isOpenAt(). A closed branch
     *   still takes the order (it lands in 'paid', same as any other order,
     *   the existing "awaiting staff" state), it just isn't escalated
     *   (EscalateUnacknowledgedOrders) while closed, and the caller is
     *   expected to have already told the customer it'll be handled at the
     *   next opening — see CheckoutController::show()/confirmation(). Only
     *   is_active / is_accepting_orders (the manual kill switch) actually
     *   blocks placement here.
     * - unavailable_until auto-restore: checked live off is_available as
     *   currently stored, not recomputed from unavailable_until here — the
     *   restore job itself is a separate scheduled-job deliverable.
     * - saving the address to the customer's address book: this only
     *   snapshots it onto the order; persisting to customer_addresses is a
     *   distinct, opt-in action the caller would trigger separately.
     * - a Paystack `payments` row: created during Paystack transaction
     *   initialisation (app/Services/Payments/**), not here — only
     *   `payment_method === 'paystack'` (web checkout) ever goes through
     *   that flow. Manually-settled methods (Order::MANUALLY_SETTLED_
     *   PAYMENT_METHODS — cash, and momo's in-house POS payments) get
     *   their `payments` row here instead, since there's no separate
     *   init step for either.
     */
    public function create(PlaceOrderData $data): Order
    {
        if (empty($data->items)) {
            throw OrderPlacementException::emptyCart();
        }

        $branch = Branch::findOrFail($data->branchId);

        if (! $branch->is_active || ! $branch->is_accepting_orders) {
            throw OrderPlacementException::branchNotAccepting();
        }

        return DB::transaction(function () use ($data, $branch) {
            $customer = $this->customers->findOrCreateByPhone($data->customerPhone, $data->customerName);

            [$itemRows, $subtotal] = $this->pricing->priceItems($branch, $data->items);

            // Re-validated here even though checkout's "Apply" button
            // already checked it live — that check only ever priced the
            // cart's subtotal at that moment, never trust it as the final
            // word. See PromotionService::validate().
            $promotion = null;
            $discountTotal = 0;

            if ($data->promoCode !== null) {
                $promotion = $this->promotions->validate($data->promoCode, $branch, $customer, $subtotal);
                $discountTotal = $this->promotions->calculateDiscount($promotion, $subtotal);
            }

            [$deliveryFee, $addressSnapshot] = $data->fulfilmentType === 'delivery'
                ? $this->resolveDelivery($branch, $data, $subtotal)
                : [0, null];

            $total = $subtotal - $discountTotal + $deliveryFee;

            $manuallySettled = in_array($data->paymentMethod, Order::MANUALLY_SETTLED_PAYMENT_METHODS, true);
            $initialStatus = $manuallySettled ? 'paid' : 'pending_payment';

            // payment_status timing differs by method (payments.md):
            // - cash collected in person (pickup) is reconciled the moment
            //   staff takes it — the same instant the order is placed.
            // - cash collected at the door (delivery) can't be reconciled
            //   until the rider actually gets there — stays 'pending' until
            //   OrderStateMachine's delivered transition confirms it.
            // - momo is reconciled by transaction ID, not by when/how it's
            //   collected — 'paid' immediately if staff entered one now,
            //   'pending' if they skipped it (busy-hour flow; entered later
            //   via PaymentConfirmationService::confirmMomo()).
            $initialPaymentStatus = match (true) {
                $data->paymentMethod === 'cash' && $data->fulfilmentType === 'pickup' => 'paid',
                $data->paymentMethod === 'momo' && $data->paymentReference !== null => 'paid',
                default => 'pending',
            };

            $order = new Order([
                'reference' => $this->generateReference($data->channel),
                'track_token' => Str::random(32),
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'fulfilment_type' => $data->fulfilmentType,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'delivery_fee' => $deliveryFee,
                'promotion_id' => $promotion?->id,
                'total' => $total,
                'payment_method' => $data->paymentMethod,
                'payment_status' => $initialPaymentStatus,
                'channel' => $data->channel,
                'delivery_address_snapshot' => $addressSnapshot,
                'instructions' => $data->instructions,
                'scheduled_for' => $data->scheduledFor,
            ]);
            $order->status = $initialStatus;
            $order->placed_at = now();
            $order->save();

            if ($promotion) {
                $this->promotions->redeem($promotion, $order, $customer, $discountTotal);
            }

            foreach ($itemRows as $row) {
                $orderItem = $order->items()->create([
                    'menu_item_id' => $row['menu_item_id'],
                    'name_snapshot' => $row['name_snapshot'],
                    'unit_price_snapshot' => $row['unit_price_snapshot'],
                    'quantity' => $row['quantity'],
                    'line_total' => $row['line_total'],
                    'notes' => $row['notes'],
                ]);

                foreach ($row['options'] as $option) {
                    $orderItem->options()->create($option);
                }
            }

            if ($manuallySettled) {
                $order->payments()->create([
                    'provider' => $data->paymentMethod,
                    'provider_reference' => $data->paymentMethod === 'momo' ? $data->paymentReference : null,
                    'amount' => $total,
                    'currency' => 'GHS',
                    'status' => $initialPaymentStatus,
                    'verified_at' => $initialPaymentStatus === 'paid' ? now() : null,
                ]);
            }

            $order->events()->create([
                'from_status' => null,
                'to_status' => $initialStatus,
                'actor_type' => $data->actorType ?? 'customer',
                'actor_id' => $data->actorId ?? $customer->id,
                'meta' => ['action' => 'placed'],
            ]);

            // SafeBroadcast::afterCommit, not a bare DB::afterCommit — see
            // its own docblock for why a broadcast failure must never
            // surface as a failure of order placement itself.
            $orderId = $order->id;
            $branchId = $order->branch_id;
            SafeBroadcast::afterCommit(fn () => OrderPlaced::dispatch($orderId, $branchId));

            return $order->fresh(['items.options', 'events', 'payments']);
        });
    }

    /**
     * @return array{0: int, 1: ?array<string, mixed>}
     */
    private function resolveDelivery(Branch $branch, PlaceOrderData $data, int $subtotal): array
    {
        $address = $data->deliveryAddress;

        if (! $address) {
            throw OrderPlacementException::deliveryAddressRequired();
        }

        if ($subtotal < DeliveryFeeCalculator::MINIMUM_ORDER_TOTAL_PESEWAS) {
            throw OrderPlacementException::belowMinimumOrderTotal(DeliveryFeeCalculator::MINIMUM_ORDER_TOTAL_PESEWAS);
        }

        // A label for the rider only — validated here too (not just in the
        // web form) since this service is meant to be callable from
        // anywhere per CLAUDE.md's API-first rule, and shouldn't trust a
        // caller to have already checked it. areaOther (the customer's own
        // area wasn't in the dropdown) becomes a real row via firstOrCreate
        // — a deliberately light-touch way to grow the shared list rather
        // than rejecting an order over it.
        $area = match (true) {
            $address->areaId !== null => DeliveryArea::where('is_active', true)->find($address->areaId),
            $address->areaOther !== null => DeliveryArea::firstOrCreate(['name' => trim($address->areaOther)]),
            default => null,
        };

        if ($address->areaId !== null && ! $area) {
            throw OrderPlacementException::invalidDeliveryArea();
        }

        // Price it now if we can — location was captured at checkout, so
        // there's no reason to make the customer wait until delivery to
        // see the real fee. Only falls back to "unknown for now" (0, priced
        // later at the delivered transition) when geolocation genuinely
        // couldn't be captured.
        $deliveryFee = ($address->lat !== null && $address->lng !== null)
            ? $this->feeCalculator->calculate($branch, $address->lat, $address->lng)
            : 0;

        return [
            $deliveryFee,
            [
                'area_id' => $area?->id,
                'area_name' => $area?->name,
                'ghanapost_code' => $address->ghanapostCode,
                'landmark' => $address->landmark,
                'lat' => $address->lat,
                'lng' => $address->lng,
            ],
        ];
    }

    /**
     * $channel ('web'|'pos') picks which admin-editable prefix applies —
     * see SettingsController. Defaults (YMGS-WEB / YMGS-POS) apply until
     * an owner sets their own, so a fresh install works with no seeding
     * required. The random suffix means a collision is astronomically
     * unlikely at this business's order volume, but orders.reference is
     * still a unique column — regenerating on the rare clash is cheap
     * insurance against a placement failing outright over it.
     */
    private function generateReference(string $channel): string
    {
        $prefix = $channel === 'pos'
            ? $this->settings->get(SettingsService::ORDER_REFERENCE_PREFIX_POS, 'YMGS-POS')
            : $this->settings->get(SettingsService::ORDER_REFERENCE_PREFIX_WEB, 'YMGS-WEB');

        do {
            $code = collect(range(1, 6))
                ->map(fn () => self::REFERENCE_CODE_ALPHABET[random_int(0, strlen(self::REFERENCE_CODE_ALPHABET) - 1)])
                ->implode('');

            $reference = "{$prefix}-{$code}";
        } while (Order::where('reference', $reference)->exists());

        return $reference;
    }
}
