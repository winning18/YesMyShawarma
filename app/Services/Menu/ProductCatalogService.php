<?php

namespace App\Services\Menu;

use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\ProductPack;
use App\Services\Settings\BusinessType;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Products for pharmacies and shops (owner-approved 2026-11-27; plan step
 * R2). A product is a menu item with:
 * - a SKU (product code): optional; left blank, it's made from the item's
 *   id (P-00042), so every product has a code for imports and reports;
 * - a barcode, the unit it's sold in ("tablet", "piece") and its cost;
 * - pack sizes (ProductPack), each with its own price and barcode.
 *
 * A barcode is unique across every product and pack, so a scan finds one
 * thing (findByCode()). Amounts come in cedis and are stored as pesewas.
 */
class ProductCatalogService
{
    public const SKU_PREFIX = 'P-';

    /**
     * The product fields from a form or an import row (cedis in, pesewas
     * out), checked against every other product and pack.
     *
     * @param  array{sku?: ?string, barcode?: ?string, unit_name?: ?string, cost_price?: string|float|int|null}  $input
     * @return array{sku: ?string, barcode: ?string, unit_name: ?string, cost_price: ?int}
     */
    public function productFields(array $input, ?MenuItem $item = null, string $field = ''): array
    {
        $sku = $this->clean($input['sku'] ?? null);
        $barcode = $this->clean($input['barcode'] ?? null);

        if ($sku !== null && MenuItem::withTrashed()->where('sku', $sku)->when($item, fn ($q) => $q->whereKeyNot($item->id))->exists()) {
            throw ValidationException::withMessages([$field.'sku' => __('The product code :sku is already used by another product. Use a different code, or leave it blank to have one made.', ['sku' => $sku])]);
        }

        if ($barcode !== null && $this->barcodeTaken($barcode, ignoreItemId: $item?->id)) {
            throw ValidationException::withMessages([$field.'barcode' => __('The barcode :barcode is already on another product or pack. Check the number, or remove it from the other one first.', ['barcode' => $barcode])]);
        }

        $cost = $input['cost_price'] ?? null;

        $fields = [
            'sku' => $sku,
            'barcode' => $barcode,
            'unit_name' => $this->clean($input['unit_name'] ?? null),
            'cost_price' => $cost === null || $cost === '' ? null : Money::toPesewas($cost),
        ];

        // A pharmacy's medicine details (R5).
        if (app(BusinessType::class)->current() === BusinessType::PHARMACY) {
            foreach (['generic_name', 'strength', 'dosage_form', 'fda_number', 'medicine_class'] as $field) {
                $fields[$field] = $this->clean($input[$field] ?? null);
            }
        }

        return $fields;
    }

    /** A product saved without a code gets one made from its id. */
    public function assignSku(MenuItem $item): void
    {
        if ($item->sku !== null) {
            return;
        }

        $sku = self::SKU_PREFIX.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT);
        // Someone may already have typed that code on another product.
        for ($n = 2; MenuItem::withTrashed()->where('sku', $sku)->exists(); $n++) {
            $sku = self::SKU_PREFIX.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT).'-'.$n;
        }

        $item->forceFill(['sku' => $sku])->save();
    }

    /**
     * @param  array{name: string, units: int|string, price: string|float|int, cost_price?: string|float|int|null, barcode?: ?string, sort_order?: int|string|null}  $input  cedis
     */
    public function savePack(MenuItem $item, array $input, ?ProductPack $pack = null): ProductPack
    {
        $name = trim($input['name']);
        $barcode = $this->clean($input['barcode'] ?? null);

        if ($item->packs()->where('name', $name)->when($pack, fn ($q) => $q->whereKeyNot($pack->id))->exists()) {
            throw ValidationException::withMessages(['pack_name' => __(':product already has a pack called :name. Give this one a different name.', ['product' => $item->name, 'name' => $name])]);
        }

        if ($barcode !== null && $this->barcodeTaken($barcode, ignorePackId: $pack?->id)) {
            throw ValidationException::withMessages(['pack_barcode' => __('The barcode :barcode is already on another product or pack. Check the number, or remove it from the other one first.', ['barcode' => $barcode])]);
        }

        $cost = $input['cost_price'] ?? null;
        $values = [
            'name' => $name,
            'units' => (int) $input['units'],
            'price' => Money::toPesewas($input['price']),
            'cost_price' => $cost === null || $cost === '' ? null : Money::toPesewas($cost),
            'barcode' => $barcode,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ];

        if ($pack) {
            $pack->update($values);

            return $pack;
        }

        return $item->packs()->create($values);
    }

    public function removePack(ProductPack $pack): void
    {
        $pack->delete();
    }

    public function barcodeTaken(string $barcode, ?int $ignoreItemId = null, ?int $ignorePackId = null): bool
    {
        return MenuItem::withTrashed()->where('barcode', $barcode)->when($ignoreItemId, fn ($q) => $q->whereKeyNot($ignoreItemId))->exists()
            || ProductPack::where('barcode', $barcode)->when($ignorePackId, fn ($q) => $q->whereKeyNot($ignorePackId))->exists();
    }

    /**
     * What a scanned or typed code is: a product (by barcode or SKU) or one
     * of its packs (by barcode). For the scan-first POS (R3).
     *
     * @return array{item: MenuItem, pack: ?ProductPack}|null
     */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        if ($item = MenuItem::where('barcode', $code)->orWhere('sku', $code)->first()) {
            return ['item' => $item, 'pack' => null];
        }

        $pack = ProductPack::with('menuItem')->where('barcode', $code)->first();

        return $pack && $pack->menuItem ? ['item' => $pack->menuItem, 'pack' => $pack] : null;
    }

    /**
     * The Products list (R2): search by name, product code or barcode
     * (a pack's too), optionally one category, 50 a page, with this
     * branch's availability.
     *
     * @return LengthAwarePaginator<int, MenuItem>
     */
    public function search(Branch $branch, ?string $query = null, ?int $categoryId = null): LengthAwarePaginator
    {
        $query = trim((string) $query);

        return MenuItem::query()
            ->with(['category', 'branches' => fn ($q) => $q->where('branches.id', $branch->id)])
            ->withCount('packs')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($query !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.addcslashes($query, '%_\\').'%')
                ->orWhere('sku', $query)
                ->orWhere('barcode', $query)
                ->orWhereHas('packs', fn ($q) => $q->where('barcode', $query))))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
