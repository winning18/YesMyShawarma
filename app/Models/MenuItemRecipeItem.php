<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one unit of a menu item (or a customer-chosen option) consumes from
 * a branch's own stock — explicit, staff-configured per branch (see
 * RecipeManagementController), not guessed. See RecipeStockService for how
 * this gets read back at order-acceptance time, and payments.md/orders.md
 * for the broader stock-deduction picture.
 */
#[Fillable(['branch_id', 'source_type', 'source_menu_item_id', 'source_option_id', 'stock_item_id', 'quantity'])]
#[ScopedBy([BranchScope::class])]
class MenuItemRecipeItem extends Model
{
    public const SOURCE_MENU_ITEM = 'menu_item';

    public const SOURCE_OPTION = 'option';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sourceMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'source_menu_item_id');
    }

    public function sourceOption(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'source_option_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
