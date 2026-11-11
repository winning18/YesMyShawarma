<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['market_list_id', 'expense_category_id', 'description', 'quantity', 'unit', 'amount'])]
class MarketListItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'amount' => 'integer',
        ];
    }

    public function marketList(): BelongsTo
    {
        return $this->belongsTo(MarketList::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
