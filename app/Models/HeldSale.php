<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sale parked at a pharmacy's or shop's till (R3, 2026-11-27), recalled
 * later by any cashier at the branch (HeldSaleService). `cart` is the POS
 * cart as it was. Cleared when its till closes.
 */
#[Fillable(['branch_id', 'terminal_id', 'user_id', 'label', 'cart'])]
#[ScopedBy([BranchScope::class])]
class HeldSale extends Model
{
    protected function casts(): array
    {
        return ['cart' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }
}
