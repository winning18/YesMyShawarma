<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sms_broadcast_id', 'customer_id', 'phone', 'opt_out_code', 'status', 'error', 'sent_at'])]
class SmsBroadcastRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(SmsBroadcast::class, 'sms_broadcast_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
