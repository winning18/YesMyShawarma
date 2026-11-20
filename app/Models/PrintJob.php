<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing for the print agent to print (step 8b): a receipt, a kitchen
 * ticket, or a cash drawer kick — see PrintJobService.
 */
#[Fillable(['branch_id', 'printer_id', 'order_id', 'kind', 'status', 'attempts', 'error', 'requested_by_user_id', 'claimed_at', 'printed_at'])]
class PrintJob extends Model
{
    public const RECEIPT = 'receipt';

    public const KITCHEN = 'kitchen';

    public const DRAWER = 'drawer';

    /** "Print a test page" on the Printers page — no order. */
    public const TEST = 'test';

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const PRINTED = 'printed';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'printer_id' => 'integer',
            'order_id' => 'integer',
            'attempts' => 'integer',
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
