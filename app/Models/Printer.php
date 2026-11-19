<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branch's receipt or kitchen printer (Express POS step 8a). See
 * PrinterService for which prints where.
 */
#[Fillable(['branch_id', 'name', 'role', 'paper_width', 'connection', 'address', 'opens_drawer', 'is_active'])]
class Printer extends Model
{
    public const RECEIPT = 'receipt';

    public const KITCHEN = 'kitchen';

    public const ROLES = [self::RECEIPT, self::KITCHEN];

    public const PAPER_WIDTHS = [58, 80];

    public const CONNECTIONS = ['network', 'usb'];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'paper_width' => 'integer',
            'opens_drawer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
