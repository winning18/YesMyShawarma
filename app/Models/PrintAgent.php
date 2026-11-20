<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Express POS Print Agent on a branch PC (step 8b) — see
 * PrintAgentService.
 */
#[Fillable(['branch_id', 'name', 'token_hash', 'pairing_code_hash', 'pairing_expires_at', 'last_seen_at'])]
#[Hidden(['token_hash', 'pairing_code_hash'])]
class PrintAgent extends Model
{
    /** Seen within this many seconds = online. It checks in every few seconds. */
    public const ONLINE_SECONDS = 60;

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'pairing_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isConnected(): bool
    {
        return $this->token_hash !== null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_SECONDS));
    }
}
