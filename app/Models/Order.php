<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference', 'track_token', 'customer_id', 'branch_id',
    'fulfilment_type',
    'subtotal', 'discount_total', 'delivery_fee', 'total',
    'promotion_id', 'payment_method', 'payment_status', 'channel', 'delivery_address_snapshot',
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
     * 'paystack' itself goes through PaystackPaymentService.
     *
     * @var list<string>
     */
    public const MANUALLY_SETTLED_PAYMENT_METHODS = ['cash', 'momo'];

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

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
