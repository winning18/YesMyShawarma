<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id', 'order_id', 'stock_item_id', 'reported_by', 'reporter_role',
    'description', 'photo_path', 'photo_viewed_at', 'status',
    'reviewed_by', 'reviewed_at', 'review_note',
])]
#[ScopedBy([BranchScope::class])]
class DamageReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    /**
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_DENIED];

    public const ROLE_RIDER = 'rider';

    public const ROLE_STAFF = 'staff';

    protected function casts(): array
    {
        return [
            'photo_viewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
