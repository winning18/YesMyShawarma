<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\ProductPack;
use App\Services\Menu\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A product's pack sizes (R2, 2026-11-27), on its edit page: pharmacies
 * and shops only (the `retail` route middleware). ProductCatalogService
 * does the work.
 */
class ProductPackController extends Controller
{
    public function store(Request $request, MenuItem $menuItem, ProductCatalogService $products): RedirectResponse
    {
        Gate::authorize('menu.edit_content');

        $pack = $products->savePack($menuItem, $this->validated($request));

        return redirect()->to(route('dashboard.menu-items.edit', $menuItem).'#packs')
            ->with('status', __(':name added.', ['name' => $pack->name]));
    }

    public function update(Request $request, MenuItem $menuItem, ProductPack $pack, ProductCatalogService $products): RedirectResponse
    {
        Gate::authorize('menu.edit_content');
        abort_unless($pack->menu_item_id === $menuItem->id, 404);

        $products->savePack($menuItem, $this->validated($request), $pack);

        return redirect()->to(route('dashboard.menu-items.edit', $menuItem).'#packs')
            ->with('status', __(':name saved.', ['name' => $pack->name]));
    }

    public function destroy(MenuItem $menuItem, ProductPack $pack, ProductCatalogService $products): RedirectResponse
    {
        Gate::authorize('menu.edit_content');
        abort_unless($pack->menu_item_id === $menuItem->id, 404);

        $products->removePack($pack);

        return redirect()->to(route('dashboard.menu-items.edit', $menuItem).'#packs')
            ->with('status', __(':name removed.', ['name' => $pack->name]));
    }

    /**
     * @return array{name: string, units: string, price: string, cost_price: ?string, barcode: ?string}
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'pack_name' => ['required', 'string', 'max:60'],
            'pack_units' => ['required', 'integer', 'min:1', 'max:100000'],
            'pack_price' => ['required', 'numeric', 'min:0.01'],
            'pack_cost_price' => ['nullable', 'numeric', 'min:0'],
            'pack_barcode' => ['nullable', 'string', 'max:64', 'regex:/^[0-9A-Za-z-]+$/'],
        ], [
            'pack_units.min' => __('A pack holds at least 1. Enter how many units are in it, e.g. 10 for a strip of 10.'),
            'pack_barcode.regex' => __('A barcode is letters, digits and dashes only. Scan it again or type it as printed.'),
        ], [
            'pack_name' => __('pack name'), 'pack_units' => __('units in the pack'), 'pack_price' => __('pack price'),
            'pack_cost_price' => __('pack cost'), 'pack_barcode' => __('pack barcode'),
        ]);

        return ['name' => $v['pack_name'], 'units' => $v['pack_units'], 'price' => $v['pack_price'], 'cost_price' => $v['pack_cost_price'] ?? null, 'barcode' => $v['pack_barcode'] ?? null];
    }
}
