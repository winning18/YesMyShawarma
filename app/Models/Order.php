<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reference', 'track_token', 'customer_id', 'branch_id',
    'fulfilment_type',
    'subtotal', 'discount_total', 'delivery_fee', 'tax_total', 'total',
    'promotion_id', 'payment_method', 'delivery_fee_paid_by', 'payment_status', 'channel', 'source', 'delivery_address_snapshot',
    'instructions', 'scheduled_for',
])]
#[ScopedBy([BranchScope::class])]
class Order extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const NON_REVENUE_STATUSES = ['cancelled', 'rejected', 'abandoned', 'refunded'];

    /**
     * Every status in the state machine (see .claude/rules/orders.md),
     * state-diagram order — the full set a history filter needs to offer,
     * as distinct from OrderStateMachine::TRANSITIONS which only encodes
     * legal from→to edges.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'pending_payment', 'paid', 'accepted', 'preparing', 'ready', 'dispatched', 'delivered',
        'rejected', 'cancelled', 'failed', 'abandoned', 'refunded',
    ];

    /**
     * Methods settled by a person, not a gateway — no online transaction to
     * wait on, so the order enters straight at 'paid' (OrderCreationService)
     * instead of 'pending_payment'. Momo here is an in-house manual
     * payment (staff confirm the customer sent it) — not Paystack; only
     * 'paystack' itself goes through PaystackPaymentService. bolt_food is
     * settled too, in the sense that Bolt already collected the customer's
     * money before the order ever reaches us — staff is just logging that
     * the kitchen needs to make it, not collecting anything themselves.
     *
     * @var list<string>
     */
    public const MANUALLY_SETTLED_PAYMENT_METHODS = ['cash', 'momo', 'bolt_food', self::COMPLIMENTARY];

    public const BOLT_FOOD = 'bolt_food';

    /**
     * A freebie given to a customer or staff member (owner, 2026-11-14):
     * the customer pays nothing, it's never sales, and it's recorded in
     * complimentary_orders with who got it, why, and a manager's approval.
     * See FreebieService.
     */
    public const COMPLIMENTARY = 'complimentary';

    /**
     * orders.source: an order taken from the WhatsApp group gets GH₵10
     * (SettingsService::WHATSAPP_REWARD_AMOUNT) off its food, held in
     * discount_total (owner, 2026-11-14). Null is every other order.
     */
    public const SOURCE_WHATSAPP = 'whatsapp';

    /** delivery_fee_paid_by values — POS MoMo delivery orders only (see the migration). */
    public const DELIVERY_FEE_PAID_BY = ['momo', 'rider_cash'];

    /**
     * Excluded from every revenue figure (Today report, Performance,
     * Invoices and sales, and the shift-end cash-reconciliation check) —
     * Bolt pays Bolt Food orders out separately, so this money never
     * touches a branch's till and staff should never be asked to account
     * for it as "today's sales". Still a real order everywhere else:
     * order counts, operational stats, and the raw order history/exports
     * all still include it. Freebies (COMPLIMENTARY) are excluded the same
     * way — nobody paid for them. Code that means "Bolt Food orders" must
     * test BOLT_FOOD, never this list.
     *
     * @var list<string>
     */
    public const EXCLUDED_FROM_SALES_PAYMENT_METHODS = [self::BOLT_FOOD, self::COMPLIMENTARY];

    protected function casts(): array
    {
        return [
            'delivery_address_snapshot' => 'array',
            'claimed_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'placed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'arrived_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The till that took the money (step 7a) — a counter's, or the shared shift. */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * The freebie record, when payment_method is COMPLIMENTARY.
     */
    public function complimentary(): HasOne
    {
        return $this->hasOne(ComplimentaryOrder::class);
    }

    /**
     * The hand-in this order's cash delivery fee went into, if any.
     */
    public function feeCollection(): HasOne
    {
        return $this->hasOne(DeliveryFeeCollectionOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** The tax lines as they were at the sale (Express POS step 9). */
    public function taxes(): HasMany
    {
        return $this->hasMany(OrderTax::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Every `{order}` route (dashboard.orders.show, accept, reject,
     * advance, cancel, assignRider, confirmMomoPayment, transferBranch,
     * adjustDeliveryFee, arrive, refunds.store) is reached by a specific
     * id, not derived from a BranchScope-filtered list — and every one of
     * them re-checks the order's own branch_id through OrderPolicy
     * (checkAtOrderBranch, or arrive()'s own rider_id check), the same
     * "correct against this order, not the ambient session" reasoning
     * OrderPolicy's own docblock already documents. Without this override,
     * a rider or manager who has since switched to a *different* branch
     * than the one an in-flight order belongs to — routine now that riders
     * can hold the role at more than one branch (orders.md's rider
     * assignment section) — would get a bare "No query results for model"
     * 404 instead of ever reaching that policy check.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->resolveRouteBindingQuery($this, $value, $field)
            ->withoutGlobalScope(BranchScope::class)
            ->first();
    }
}
