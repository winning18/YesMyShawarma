<?php

namespace App\Services\Pos;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ProductPack;
use App\Services\Menu\ProductCatalogService;
use App\Services\Stock\ProductStockService;
use Illuminate\Validation\ValidationException;

/**
 * The scan-first POS for pharmacies and shops (plan step R3): finding a
 * product by name, code or barcode among thousands, only what this branch
 * can sell right now.
 */
class RetailPosService
{
    public const RESULTS = 20;

    public function __construct(private readonly ProductCatalogService $products) {}

    /**
     * @return list<array{id: int, name: string, sku: ?string, price: int, unit: ?string, packs: list<array{id: int, name: string, units: int, price: int}>}>
     */
    public function search(Branch $branch, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $found = $this->sellable($branch)
            ->where(fn ($q) => $q
                ->where('menu_items.name', 'like', '%'.addcslashes($query, '%_\\').'%')
                ->orWhere('menu_items.sku', $query)
                ->orWhere('menu_items.barcode', $query))
            ->with('packs')
            ->orderByRaw('menu_items.name like ? desc', [addcslashes($query, '%_\\').'%'])
            ->orderBy('menu_items.name')
            ->limit(self::RESULTS)
            ->get();
        $stock = app(ProductStockService::class)->onHand($branch->id, $found->pluck('id')->all());

        return $found->map(fn (MenuItem $item) => [...$this->present($item), 'stock' => $stock[$item->id] ?? 0.0])->all();
    }

    /**
     * What a scanned code sells here: the product, or one of its packs.
     *
     * @return array{item: MenuItem, pack: ?ProductPack}
     */
    public function scan(Branch $branch, string $code): array
    {
        $found = $this->products->findByCode($code);

        if (! $found) {
            // A receipt scanned here by mistake: say where it goes.
            if (Order::where('branch_id', $branch->id)->where('reference', strtoupper(trim($code)))->exists()) {
                throw ValidationException::withMessages(['code' => __('That\'s receipt :code. To take items back, press Return and scan it there.', ['code' => strtoupper(trim($code))])]);
            }

            throw ValidationException::withMessages(['code' => __('Nothing has the code :code. Check it, or search by name.', ['code' => trim($code)])]);
        }
        if (! $this->sellable($branch)->whereKey($found['item']->id)->exists()) {
            throw ValidationException::withMessages(['code' => __(':name isn\'t on sale at this branch right now (switched off or sold out).', ['name' => $found['item']->name])]);
        }

        return $found;
    }

    /** @return array{id: int, name: string, sku: ?string, price: int, unit: ?string, packs: list<array{id: int, name: string, units: int, price: int}>} */
    public function present(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'price' => (int) $item->base_price,
            'unit' => $item->unit_name,
            'packs' => $item->packs->map(fn (ProductPack $p) => ['id' => $p->id, 'name' => $p->name, 'units' => $p->units, 'price' => $p->price])->values()->all(),
        ];
    }

    /**
     * The till's warning when a sale takes more than the system shows
     * (owner, 2026-11-27: warn, never block). Null when there's enough.
     *
     * @param  list<array<string, mixed>>  $lines  the cart's priced lines
     */
    public function stockWarning(Branch $branch, int $menuItemId, array $lines): ?string
    {
        $wanted = collect($lines)->where('menu_item_id', $menuItemId)->sum(fn (array $l) => $l['quantity'] * ($l['units'] ?? 1));
        $onHand = app(ProductStockService::class)->onHand($branch->id, [$menuItemId])[$menuItemId];

        if ($wanted <= $onHand) {
            return null;
        }
        $name = MenuItem::withTrashed()->find($menuItemId)?->name;

        return __('Stock shows :on_hand :name, and this sale has :wanted. Sold anyway; check the shelf or count it.', ['on_hand' => rtrim(rtrim(number_format($onHand, 2, '.', ''), '0'), '.'), 'name' => $name, 'wanted' => $wanted]);
    }

    private function sellable(Branch $branch)
    {
        return $branch->menuItems()->where('menu_items.is_active', true)->wherePivot('is_available', true);
    }
}
