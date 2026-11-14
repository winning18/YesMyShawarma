<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A freebie's record: who got it, why, who gave it and its approval
 * (owner, 2026-11-14). The order itself (payment_method 'complimentary')
 * holds the items and their menu value. See FreebieService.
 */
#[Fillable([
    'order_id', 'recipient_type', 'recipient_user_id', 'recipient_name', 'reason', 'status',
    'recorded_by', 'recorder_role', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class ComplimentaryOrder extends Model
{
    public const RECIPIENT_CUSTOMER = 'customer';

    public const RECIPIENT_STAFF = 'staff';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id')->withTrashed();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * Who it was for, as a person would say it.
     */
    public function recipientLabel(): string
    {
        return $this->recipient_type === self::RECIPIENT_STAFF
            ? ($this->recipientUser?->name ?? __('Staff member'))
            : ($this->recipient_name ?: __('Customer'));
    }
}
