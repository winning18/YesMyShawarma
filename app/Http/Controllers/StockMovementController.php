<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Models\StockItem;
use App\Services\Shifts\ShiftService;
use App\Services\Stock\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Staff side of stock management — recording a sale/usage against a
 * branch's stock. stock.record_sale, held by everyone who works a branch
 * day-to-day except rider (permissions.md).
 */
class StockMovementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('stock.record_sale');

        return view('dashboard.stock.movements', [
            'items' => StockItem::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, StockItem $stockItem, StockService $stock, ShiftService $shifts): RedirectResponse
    {
        Gate::authorize('stock.record_sale');

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        try {
            $stock->recordSale($stockItem, $user, $validated['quantity'], $shifts->activeFor($user)?->id, $validated['note'] ?? null);
        } catch (StockException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('status', __('Sale recorded.'));
    }

    public function history(StockItem $stockItem): View
    {
        Gate::authorize('stock.record_sale');

        return view('dashboard.stock.history', [
            'item' => $stockItem,
            'movements' => $stockItem->movements()->with('actor')->latest('id')->paginate(30),
        ]);
    }
}
