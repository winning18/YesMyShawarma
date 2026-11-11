<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One market trip (or one shift close's expenses): its items, who
 * recorded it, and where it is in approval. Only approved lists count in
 * reports. See schema.md's "Market list" section.
 */
#[Fillable([
    'branch_id', 'purchased_on', 'recorded_by', 'recorder_role', 'shift_id',
    'status', 'total', 'receipt_path', 'note',
    'reviewed_by', 'reviewed_at', 'review_note',
])]
#[ScopedBy([BranchScope::class])]
class MarketList extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_NEEDS_CHANGES = 'needs_changes';

    public const STATUS_APPROVED = 'approved';

    /**
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_NEEDS_CHANGES, self::STATUS_APPROVED];

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'reviewed_at' => 'datetime',
            'total' => 'integer',
        ];
    }

    /**
     * Unscoped, like Order's: a GM or owner acts on lists from branches
     * other than the one in their session, and MarketListPolicy checks the
     * list's own branch straight after.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->resolveRouteBindingQuery($this, $value, $field)
            ->withoutGlobalScope(BranchScope::class)
            ->first();
    }

    public function isEditable(): bool
    {
        return $this->status !== self::STATUS_APPROVED;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketListItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketListEvent::class);
    }
}
