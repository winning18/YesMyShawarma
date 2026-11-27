<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_item_id', 'type', 'quantity', 'actor_id', 'shift_id', 'source_type', 'source_id', 'note'])]
class StockMovement extends Model
{
    public const TYPE_RESTOCK = 'restock';

    public const TYPE_SALE = 'sale';

    // Pharmacies and shops (R4). `quantity` is how much moved; the type says
    // which way, except `count`, whose quantity is the signed correction.
    public const TYPE_RECEIVED = 'received';

    public const TYPE_COUNT = 'count';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_RETURNED = 'returned';

    /** An expired batch written off (R5). */
    public const TYPE_EXPIRED = 'expired';

    /** What caused a movement (source_type). */
    public const SOURCE_ORDER = 'order';

    public const SOURCE_PURCHASE_ORDER = 'purchase_order';

    public const SOURCE_STOCK_COUNT = 'stock_count';

    public const SOURCE_TRANSFER = 'stock_transfer';

    public const SOURCE_REFUND = 'refund';

    public const SOURCE_BATCH = 'stock_batch';

    /** Types that take stock away. */
    public const OUTGOING = [self::TYPE_SALE, self::TYPE_TRANSFER_OUT, self::TYPE_EXPIRED];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
