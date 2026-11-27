<?php

namespace App\Services\Menu;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\ProductPack;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * A pharmacy's or shop's product list from a spreadsheet (owner-approved
 * 2026-11-27; plan step R2): CSV or Excel (.xlsx), one product per row,
 * from the Products page or `expresspos:import-products` at onboarding.
 *
 * - Every row is checked first; with any error nothing is saved, and each
 *   error says its row, what's wrong and how to fix it.
 * - A row whose SKU is already a product updates it (so the same file can
 *   be imported again after changes); a removed product with that SKU comes
 *   back. Without a SKU, a matching barcode does the same; otherwise it's a
 *   new product, given a SKU (ProductCatalogService::assignSku()).
 * - Categories are found by name, or created.
 * - Pack columns add a pack size; more packs for the same product go on
 *   further rows with the same SKU and only the pack columns filled. A pack
 *   whose name the product already has is updated.
 * - New products are on sale at every branch, as when added by hand.
 */
class ProductImportService
{
    public const MAX_ROWS = 10000;

    /** Accepted headings (lower case, spaces or underscores) => field. */
    private const HEADINGS = [
        'name' => 'name', 'product' => 'name', 'product name' => 'name',
        'category' => 'category',
        'price' => 'price', 'selling price' => 'price',
        'sku' => 'sku', 'code' => 'sku', 'product code' => 'sku',
        'barcode' => 'barcode',
        'unit' => 'unit_name',
        'cost' => 'cost_price', 'cost price' => 'cost_price',
        'description' => 'description',
        'pack name' => 'pack_name', 'pack' => 'pack_name',
        'pack units' => 'pack_units', 'units in pack' => 'pack_units',
        'pack price' => 'pack_price',
        'pack cost' => 'pack_cost_price', 'pack cost price' => 'pack_cost_price',
        'pack barcode' => 'pack_barcode',
    ];

    public const TEMPLATE_HEADINGS = ['Name', 'Category', 'Price', 'SKU', 'Barcode', 'Unit', 'Cost price', 'Description', 'Pack name', 'Pack units', 'Pack price', 'Pack cost price', 'Pack barcode'];

    public function __construct(private readonly ProductCatalogService $products) {}

    /**
     * @return array{ok: true, created: int, updated: int, packs: int}|array{ok: false, errors: list<string>}
     */
    public function import(string $path, string $extension): array
    {
        $rows = $this->read($path, strtolower($extension));
        $errors = [];
        $products = $this->check($rows, $errors);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $counts = ['created' => 0, 'updated' => 0, 'packs' => 0];

        DB::transaction(function () use ($products, &$counts) {
            $branchIds = Branch::pluck('id');
            $categories = Category::all()->keyBy(fn (Category $c) => mb_strtolower($c->name));

            foreach ($products as $product) {
                $item = $product['existing'];
                $fields = [
                    'name' => $product['name'],
                    'base_price' => Money::toPesewas($product['price']),
                    'sku' => $product['sku'],
                    'barcode' => $product['barcode'],
                    'unit_name' => $product['unit_name'],
                    'cost_price' => $product['cost_price'] === null ? null : Money::toPesewas($product['cost_price']),
                ];
                if ($product['description'] !== null) {
                    $fields['description'] = $product['description'];
                }

                if ($product['category'] !== null) {
                    $key = mb_strtolower($product['category']);
                    $categories[$key] ??= Category::create(['name' => $product['category'], 'slug' => $this->uniqueSlug(Category::class, $product['category']), 'is_active' => true]);
                    $fields['category_id'] = $categories[$key]->id;
                }

                if ($item) {
                    if ($item->trashed()) {
                        $item->restore();
                    }
                    // A blank SKU on an update keeps the product's own code.
                    $fields['sku'] ??= $item->sku;
                    $item->update($fields);
                    $counts['updated']++;
                } else {
                    $item = MenuItem::create([...$fields, 'slug' => $this->uniqueSlug(MenuItem::class, $product['name']), 'is_active' => true]);
                    $item->branches()->attach($branchIds, ['is_available' => true]);
                    $counts['created']++;
                }
                $this->products->assignSku($item);

                foreach ($product['packs'] as $pack) {
                    $existing = $item->packs()->where('name', $pack['name'])->first();
                    $this->products->savePack($item, $pack, $existing);
                    $counts['packs']++;
                }
            }
        });

        return ['ok' => true, ...$counts];
    }

    /**
     * @return list<array{row: int, values: array<string, ?string>}>
     */
    private function read(string $path, string $extension): array
    {
        $reader = match ($extension) {
            'csv', 'txt' => new CsvReader,
            'xlsx' => new XlsxReader,
            default => throw new RuntimeException(__('The file must be a CSV or an Excel (.xlsx) file. In Excel or Google Sheets, save or download it as one of those.')),
        };

        try {
            $reader->open($path);
        } catch (\Throwable) {
            throw new RuntimeException(__('The file couldn\'t be opened. Save it again as CSV or Excel (.xlsx) and upload that.'));
        }

        $fields = null;
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $number = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $number++;
                $cells = array_map(fn ($cell) => $cell instanceof \DateTimeInterface ? $cell->format('Y-m-d') : trim((string) $cell), $row->toArray());

                if ($fields === null) {
                    $fields = $this->fields($cells);

                    continue;
                }
                if (implode('', $cells) === '') {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    $reader->close();
                    throw new RuntimeException(__('The file has more than :max products. Split it into smaller files and import them one after another.', ['max' => number_format(self::MAX_ROWS)]));
                }

                $values = [];
                foreach ($fields as $i => $field) {
                    if ($field !== null) {
                        $values[$field] = ($cells[$i] ?? '') === '' ? null : $cells[$i];
                    }
                }
                $rows[] = ['row' => $number, 'values' => $values];
            }

            break; // The first sheet only.
        }
        $reader->close();

        if ($fields === null || ! in_array('name', $fields, true)) {
            throw new RuntimeException(__('The first row must be the headings, with at least Name, Category and Price. Download the template to see them.'));
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headings
     * @return list<?string>
     */
    private function fields(array $headings): array
    {
        return array_map(fn (string $heading) => self::HEADINGS[str_replace('_', ' ', mb_strtolower(trim($heading, " \t\n\r\0\x0B\u{FEFF}")))] ?? null, $headings);
    }

    /**
     * Rows grouped into products, every error collected.
     *
     * @param  list<array{row: int, values: array<string, ?string>}>  $rows
     * @param  list<string>  $errors
     * @return list<array<string, mixed>>
     */
    private function check(array $rows, array &$errors): array
    {
        $products = [];
        $bySku = [];
        $barcodes = [];

        foreach ($rows as ['row' => $row, 'values' => $v]) {
            $fail = function (string $message) use ($row, &$errors) {
                $errors[] = __('Row :row: :message', ['row' => $row, 'message' => $message]);
            };
            $sku = $v['sku'] ?? null;
            $pack = $this->pack($v, $fail);
            $productColumns = array_filter(array_intersect_key($v, array_flip(['name', 'category', 'price', 'barcode', 'unit_name', 'cost_price', 'description'])), fn ($value) => $value !== null);

            // Another pack for a product further up the file.
            if ($sku !== null && isset($bySku[$sku])) {
                if ($productColumns !== []) {
                    $fail(__('the product code :sku is on an earlier row. To add another pack, fill in only the SKU and the pack columns; to change the product, change the earlier row.', ['sku' => $sku]));
                } elseif ($pack === null) {
                    $fail(__('this row repeats the product code :sku but has no pack. Fill in the pack columns, or remove the row.', ['sku' => $sku]));
                } else {
                    $products[$bySku[$sku]]['packs'][] = $pack;
                }
                $this->checkBarcode($pack['barcode'] ?? null, $barcodes, $fail, null, $products[$bySku[$sku]]['existing']);

                continue;
            }

            $existing = $sku !== null ? MenuItem::withTrashed()->where('sku', $sku)->first() : null;
            $existing ??= ($v['barcode'] ?? null) !== null ? MenuItem::withTrashed()->where('barcode', $v['barcode'])->first() : null;

            if (($v['name'] ?? null) === null) {
                $fail(__('the Name is empty. Every product needs a name.'));
            }
            if (($v['category'] ?? null) === null && ! $existing) {
                $fail(__('the Category is empty. Give each new product a category, e.g. Pain relief.'));
            }
            $priceWritten = ($v['price'] ?? null) !== null;
            foreach (['price' => 'Price', 'cost_price' => 'Cost price'] as $field => $label) {
                $value = $v[$field] ?? null;
                if ($value !== null && (! is_numeric($value = str_replace([',', 'GH₵', 'GHS', '₵'], '', $value)) || $value < 0)) {
                    $fail(__('the :label ":value" isn\'t an amount. Write it in cedis as a number, e.g. 12.50.', ['label' => $label, 'value' => $v[$field]]));
                    $value = null;
                }
                $v[$field] = $value;
            }
            if (! $priceWritten) {
                $fail(__('the Price is empty. Write the selling price in cedis, e.g. 12.50.'));
            } elseif ($v['price'] !== null && (float) $v['price'] <= 0) {
                $fail(__('the Price must be more than 0.'));
            }
            if ($sku !== null && mb_strlen($sku) > 64) {
                $fail(__('the SKU is longer than 64 characters. Shorten it.'));
            }
            if (mb_strlen((string) ($v['name'] ?? '')) > 255) {
                $fail(__('the Name is longer than 255 characters. Shorten it.'));
            }
            if (mb_strlen((string) ($v['unit_name'] ?? '')) > 30) {
                $fail(__('the Unit is longer than 30 characters. Use a short word, e.g. tablet or piece.'));
            }
            $this->checkBarcode($v['barcode'] ?? null, $barcodes, $fail, $existing?->id);

            $products[] = [
                'existing' => $existing,
                'name' => $v['name'] ?? '',
                'category' => $v['category'] ?? null,
                'price' => $v['price'] ?? 0,
                'sku' => $sku,
                'barcode' => $v['barcode'] ?? null,
                'unit_name' => $v['unit_name'] ?? null,
                'cost_price' => $v['cost_price'] ?? null,
                'description' => $v['description'] ?? null,
                'packs' => $pack ? [$pack] : [],
            ];
            if ($sku !== null) {
                $bySku[$sku] = array_key_last($products);
            }
            $this->checkBarcode($pack['barcode'] ?? null, $barcodes, $fail, null, $existing);
        }

        if ($rows === []) {
            $errors[] = __('The file has headings but no products. Add a row for each product under the headings.');
        }

        return $products;
    }

    /**
     * @param  array<string, ?string>  $v
     * @return array{name: string, units: string, price: string, cost_price: ?string, barcode: ?string}|null
     */
    private function pack(array $v, \Closure $fail): ?array
    {
        $columns = ['pack_name', 'pack_units', 'pack_price', 'pack_cost_price', 'pack_barcode'];
        if (array_filter(array_intersect_key($v, array_flip($columns)), fn ($value) => $value !== null) === []) {
            return null;
        }

        $ok = true;
        if (($v['pack_name'] ?? null) === null) {
            $fail(__('the Pack name is empty. Name the pack, e.g. Strip of 10 or Box.'));
            $ok = false;
        }
        if (! ctype_digit((string) ($v['pack_units'] ?? '')) || (int) $v['pack_units'] < 1) {
            $fail(__('the Pack units must be a whole number of 1 or more: how many units are in the pack, e.g. 10.'));
            $ok = false;
        }
        $price = str_replace([',', 'GH₵', 'GHS', '₵'], '', (string) ($v['pack_price'] ?? ''));
        if (! is_numeric($price) || $price <= 0) {
            $fail(__('the Pack price must be an amount in cedis, more than 0, e.g. 45.00.'));
            $ok = false;
        }
        $cost = ($v['pack_cost_price'] ?? null) === null ? null : str_replace([',', 'GH₵', 'GHS', '₵'], '', $v['pack_cost_price']);
        if ($cost !== null && (! is_numeric($cost) || $cost < 0)) {
            $fail(__('the Pack cost price must be an amount in cedis, e.g. 30.00.'));
            $ok = false;
        }

        return $ok ? ['name' => $v['pack_name'], 'units' => $v['pack_units'], 'price' => $price, 'cost_price' => $cost, 'barcode' => $v['pack_barcode'] ?? null] : null;
    }

    /**
     * @param  array<string, true>  $seen  barcodes earlier in the file
     */
    private function checkBarcode(?string $barcode, array &$seen, \Closure $fail, ?int $ownItemId, ?MenuItem $packOf = null): void
    {
        if ($barcode === null) {
            return;
        }
        if (! preg_match('/^[0-9A-Za-z-]{1,64}$/', $barcode)) {
            $fail(__('the barcode ":barcode" has characters a barcode can\'t have. Use digits, letters and dashes only.', ['barcode' => $barcode]));

            return;
        }
        if (isset($seen[$barcode])) {
            $fail(__('the barcode :barcode is on an earlier row too. Each barcode belongs to one product or pack.', ['barcode' => $barcode]));

            return;
        }
        $seen[$barcode] = true;

        // On a product or pack already in the app, other than the one this row updates.
        $ownPackIds = $packOf ? $packOf->packs()->pluck('id')->all() : [];
        $takenByItem = MenuItem::withTrashed()->where('barcode', $barcode)->when($ownItemId ?? $packOf?->id, fn ($q, $id) => $q->whereKeyNot($id))->exists();
        $takenByPack = ProductPack::where('barcode', $barcode)->whereNotIn('id', $ownPackIds)->exists();
        if ($takenByItem || $takenByPack) {
            $fail(__('the barcode :barcode is already on another product or pack. Check the number, or remove it from the other one first.', ['barcode' => $barcode]));
        }
    }

    /** @param  class-string<MenuItem|Category>  $model */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        for ($n = 2; $model::query()->when(method_exists($model, 'bootSoftDeletes'), fn ($q) => $q->withTrashed())->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /** The template's example rows: a product with two packs. */
    public function templateRows(): array
    {
        return [
            self::TEMPLATE_HEADINGS,
            ['Paracetamol 500mg', 'Pain relief', '0.50', 'PARA500', '6151100000017', 'tablet', '0.30', '', 'Strip of 10', '10', '4.50', '3.00', '6151100000024'],
            ['', '', '', 'PARA500', '', '', '', '', 'Box of 100', '100', '40.00', '28.00', '6151100000031'],
        ];
    }
}
