<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['message', 'branch_id', 'audience', 'status', 'scheduled_at', 'recipient_count', 'sent_count', 'failed_count', 'created_by', 'started_at', 'completed_at'])]
class SmsBroadcast extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Who this broadcast goes to, as ticked: everyone who's a customer
     * ("all"), customers of these branches, and/or uploaded contacts. A
     * broadcast from before the ticks (audience null) went to one branch,
     * or, with none, to every number on file including uploaded contacts.
     *
     * @return array{all: bool, branches: list<int>, uploaded: bool}
     */
    public function selection(): array
    {
        if ($this->audience !== null) {
            return [
                'all' => (bool) ($this->audience['all'] ?? false),
                'branches' => array_values(array_map('intval', $this->audience['branches'] ?? [])),
                'uploaded' => (bool) ($this->audience['uploaded'] ?? false),
            ];
        }

        return $this->branch_id !== null
            ? ['all' => false, 'branches' => [$this->branch_id], 'uploaded' => false]
            : ['all' => true, 'branches' => [], 'uploaded' => true];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsBroadcastRecipient::class);
    }
}
