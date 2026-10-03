<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Read-only movement history for a stock item — restocks and the
 * recipe-driven automatic deductions RecipeStockService records on order
 * acceptance (schema.md's "Recipes and stock deduction" section). There is
 * deliberately no manual "record a sale" action any more; stock.manage
 * (owner + stock_manager) is the only way to touch stock at all now that
 * deduction is automatic — see permissions.md's "Stock management" section.
 */
class StockMovementController extends Controller
{
    public function history(StockItem $stockItem): View
    {
        Gate::authorize('stock.manage');

        return view('dashboard.stock.history', [
            'item' => $stockItem,
            'movements' => $stockItem->movements()->with('actor')->latest('id')->paginate(30),
        ]);
    }
}
