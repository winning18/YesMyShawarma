<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable history of a market list — every submit, edit, approval and
 * send-back, with who did it.
 */
#[Fillable(['market_list_id', 'actor_id', 'action', 'note'])]
class MarketListEvent extends Model
{
    public const SUBMITTED = 'submitted';

    public const EDITED = 'edited';

    public const RESUBMITTED = 'resubmitted';

    public const SENT_BACK = 'sent_back';

    public const APPROVED = 'approved';

    public const AUTO_APPROVED = 'auto_approved';

    public const CONVERTED = 'converted';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
