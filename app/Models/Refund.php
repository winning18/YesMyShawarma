<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'branch_id', 'amount', 'reason', 'status',
    'requested_by', 'reviewed_by', 'reviewed_at', 'review_note',
    'completed_by', 'completed_at', 'provider_reference',
    // A return at a pharmacy's or shop's till (R3): its items and the till the money came from.
    'kind', 'shift_id',
])]
#[ScopedBy([BranchScope::class])]
class Refund extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    /** A refund asked for and decided (the original kind). */
    public const KIND_REFUND = 'refund';

    /** Items brought back to a pharmacy's or shop's till (R3): refund_items lists them. */
    public const KIND_RETURN = 'return';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_COMPLETED];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
