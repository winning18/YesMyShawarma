<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a combo menu item (e.g. "Signature (Chicken, Cheese & Sausage)")
 * decomposes into for daily-sales reporting — explicit, staff-configured
 * per item (see MenuItemManagementController::storeComponent()), not
 * guessed from the item's name. See DailySalesReportService for how this
 * gets read back.
 */
#[Fillable(['menu_item_id', 'component_type', 'component_menu_item_id', 'component_option_id', 'quantity'])]
class MenuItemComponent extends Model
{
    public const TYPE_BASE = 'base';

    public const TYPE_MODIFIER = 'modifier';

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function componentMenuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'component_menu_item_id');
    }

    public function componentOption(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'component_option_id');
    }
}
