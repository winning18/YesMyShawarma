<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\ProductPack;
use App\Models\User;
use App\Services\Menu\ProductCatalogService;
use App\Services\Settings\BusinessType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Products for pharmacies and shops (owner-approved 2026-11-27; plan step
 * R2): product code, barcode, unit, cost, pack sizes, and the import.
 */
class ProductsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Madina', 'slug' => 'madina', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.6, 'lng' => -0.1, 'opens_at' => '08:00', 'closes_at' => '20:00',
        ]);
        $this->category = Category::create(['name' => 'Pain relief', 'slug' => 'pain-relief']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        app(BusinessType::class)->set(BusinessType::PHARMACY);
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function product(array $fields = []): array
    {
        return ['category_id' => $this->category->id, 'name' => 'Paracetamol 500mg', 'base_price' => '0.50', 'is_active' => '1', ...$fields];
    }

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'products').'.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'products.csv', 'text/csv', null, true);
    }

    public function test_a_product_gets_a_code_when_none_is_given_and_keeps_its_fields(): void
    {
        $this->asOwner()->post(route('dashboard.menu-items.store'), $this->product(['barcode' => '6151100000017', 'unit_name' => 'tablet', 'cost_price' => '0.30']))->assertRedirect();

        $item = MenuItem::sole();
        $this->assertSame(sprintf('P-%05d', $item->id), $item->sku);
        $this->assertSame(['6151100000017', 'tablet', 30], [$item->barcode, $item->unit_name, $item->cost_price]);

        // A code typed by hand is kept; one already used is refused, saying how to fix it.
        $this->asOwner()->post(route('dashboard.menu-items.store'), $this->product(['name' => 'Ibuprofen', 'sku' => 'IBU200']));
        $this->assertTrue(MenuItem::where('sku', 'IBU200')->exists());
        $this->asOwner()->post(route('dashboard.menu-items.store'), $this->product(['name' => 'Other', 'sku' => 'IBU200']))
            ->assertSessionHasErrors(['sku' => 'The product code IBU200 is already used by another product. Use a different code, or leave it blank to have one made.']);
    }

    public function test_a_barcode_belongs_to_one_product_or_pack(): void
    {
        $products = app(ProductCatalogService::class);
        $item = MenuItem::create(['category_id' => $this->category->id, 'name' => 'Paracetamol', 'slug' => 'paracetamol', 'base_price' => 50, 'barcode' => '111']);
        $pack = $products->savePack($item, ['name' => 'Strip of 10', 'units' => 10, 'price' => '4.50', 'barcode' => '222']);
        $this->assertSame(450, $pack->price);

        $this->asOwner()->post(route('dashboard.menu-items.store'), $this->product(['name' => 'Other', 'barcode' => '222']))->assertSessionHasErrors('barcode');
        $this->asOwner()->post(route('dashboard.menu-items.packs.store', $item), ['pack_name' => 'Box', 'pack_units' => 100, 'pack_price' => '40', 'pack_barcode' => '111'])->assertSessionHasErrors('pack_barcode');
        $this->asOwner()->post(route('dashboard.menu-items.packs.store', $item), ['pack_name' => 'Strip of 10', 'pack_units' => 10, 'pack_price' => '4'])->assertSessionHasErrors('pack_name');

        // A scan finds the product or the pack.
        $this->assertNull($products->findByCode('111')['pack']);
        $this->assertSame($pack->id, $products->findByCode('222')['pack']->id);
        $this->assertSame($item->id, $products->findByCode($item->fresh()->sku ?? '111')['item']->id);
    }

    public function test_packs_are_added_changed_and_removed_on_the_product_page(): void
    {
        $item = MenuItem::create(['category_id' => $this->category->id, 'name' => 'Paracetamol', 'slug' => 'paracetamol', 'base_price' => 50, 'unit_name' => 'tablet']);

        $this->asOwner()->get(route('dashboard.menu-items.edit', $item))->assertOk()->assertSee('Pack sizes')->assertDontSee('Recorded as (daily sales reporting)');
        $this->asOwner()->post(route('dashboard.menu-items.packs.store', $item), ['pack_name' => 'Box of 100', 'pack_units' => 100, 'pack_price' => '40.00', 'pack_cost_price' => '28'])->assertRedirect();
        $pack = ProductPack::sole();
        $this->assertSame([100, 4000, 2800], [$pack->units, $pack->price, $pack->cost_price]);

        $this->asOwner()->put(route('dashboard.menu-items.packs.update', [$item, $pack]), ['pack_name' => 'Box of 100', 'pack_units' => 100, 'pack_price' => '42.00'])->assertRedirect();
        $this->assertSame(4200, $pack->fresh()->price);

        $this->asOwner()->delete(route('dashboard.menu-items.packs.destroy', [$item, $pack]))->assertRedirect();
        $this->assertSame(0, ProductPack::count());
    }

    public function test_the_product_list_searches_by_name_code_and_barcode(): void
    {
        $products = app(ProductCatalogService::class);
        $para = MenuItem::create(['category_id' => $this->category->id, 'name' => 'Paracetamol', 'slug' => 'paracetamol', 'base_price' => 50, 'sku' => 'PARA']);
        MenuItem::create(['category_id' => $this->category->id, 'name' => 'Ibuprofen', 'slug' => 'ibuprofen', 'base_price' => 80, 'barcode' => '999']);
        $products->savePack($para, ['name' => 'Box', 'units' => 100, 'price' => '40', 'barcode' => '777']);

        $this->asOwner()->get(route('dashboard.menu-items.index'))->assertOk()->assertSee('Search by name, product code or barcode')->assertSee('Paracetamol')->assertSee('Ibuprofen');
        $this->asOwner()->get(route('dashboard.menu-items.index', ['q' => 'ibu']))->assertSee('Ibuprofen')->assertDontSee('Paracetamol');
        $this->asOwner()->get(route('dashboard.menu-items.index', ['q' => '999']))->assertSee('Ibuprofen')->assertDontSee('Paracetamol');
        $this->asOwner()->get(route('dashboard.menu-items.index', ['q' => '777']))->assertSee('Paracetamol')->assertDontSee('Ibuprofen');
        $this->asOwner()->get(route('dashboard.menu-items.index', ['q' => 'PARA']))->assertSee('Paracetamol');
    }

    public function test_an_import_adds_products_categories_and_packs_then_updates_on_a_second_run(): void
    {
        $file = "Name,Category,Price,SKU,Barcode,Unit,Cost price,Pack name,Pack units,Pack price,Pack barcode\n"
            ."Paracetamol 500mg,Pain relief,0.50,PARA500,6151100000017,tablet,0.30,Strip of 10,10,4.50,6151100000024\n"
            .",,,PARA500,,,,Box of 100,100,40.00,6151100000031\n"
            ."Vitamin C 1000mg,Vitamins,\"1,200.00\",,,tablet,,,,,\n";

        $this->asOwner()->post(route('dashboard.products.import.store'), ['file' => $this->csv($file)])
            ->assertRedirect(route('dashboard.menu-items.index'))->assertSessionHas('status', '2 new products, 0 updated, 2 pack sizes.');

        $para = MenuItem::where('sku', 'PARA500')->sole();
        $this->assertSame(['Paracetamol 500mg', 50, 30, 'tablet'], [$para->name, $para->base_price, $para->cost_price, $para->unit_name]);
        $this->assertSame(['Strip of 10', 'Box of 100'], $para->packs->pluck('name')->all());
        $vitamin = MenuItem::where('name', 'Vitamin C 1000mg')->sole();
        $this->assertSame(120000, $vitamin->base_price);
        $this->assertStringStartsWith('P-', $vitamin->sku);
        $this->assertTrue(Category::where('name', 'Vitamins')->exists(), 'a new category is made');
        $this->assertTrue($this->branch->menuItems()->whereKey($vitamin->id)->exists(), 'on sale at every branch');

        // The same file again, with a new price: updated, nothing doubled.
        $this->asOwner()->post(route('dashboard.products.import.store'), ['file' => $this->csv(str_replace('0.50,PARA500', '0.60,PARA500', $file))])
            ->assertSessionHas('status', '1 new products, 1 updated, 2 pack sizes.');
        $this->assertSame(60, $para->fresh()->base_price);
        $this->assertSame(2, ProductPack::count());
    }

    public function test_an_import_with_mistakes_saves_nothing_and_lists_each_row(): void
    {
        // A barcode on a pack can't go on a product (a product's own barcode would update it instead).
        $old = MenuItem::create(['category_id' => $this->category->id, 'name' => 'Old', 'slug' => 'old', 'base_price' => 50]);
        app(ProductCatalogService::class)->savePack($old, ['name' => 'Box', 'units' => 10, 'price' => '5', 'barcode' => '555']);
        $file = "Name,Category,Price,Barcode,Pack name,Pack units,Pack price\n"
            ."Good one,Pain relief,1.00,,,,\n"
            .",Pain relief,1.00,,,,\n"
            ."No price,Pain relief,,,,,\n"
            ."Bad price,Pain relief,abc,,,,\n"
            ."Taken barcode,Pain relief,1.00,555,,,\n"
            ."Bad pack,Pain relief,1.00,,Box,zero,\n";

        $this->asOwner()->post(route('dashboard.products.import.store'), ['file' => $this->csv($file)])->assertSessionHasErrors('file');
        $errors = session('import_errors');

        $this->assertSame(1, MenuItem::count(), 'nothing imported');
        $this->assertContains('Row 3: the Name is empty. Every product needs a name.', $errors);
        $this->assertContains('Row 4: the Price is empty. Write the selling price in cedis, e.g. 12.50.', $errors);
        $this->assertContains('Row 5: the Price "abc" isn\'t an amount. Write it in cedis as a number, e.g. 12.50.', $errors);
        $this->assertContains('Row 6: the barcode 555 is already on another product or pack. Check the number, or remove it from the other one first.', $errors);
        $this->assertContains('Row 7: the Pack units must be a whole number of 1 or more: how many units are in the pack, e.g. 10.', $errors);
    }

    public function test_an_excel_file_imports_too(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'products').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Name', 'Category', 'Price', 'Barcode']));
        $writer->addRow(Row::fromValues(['Amoxicillin 500mg', 'Antibiotics', 2.5, '6151100000048']));
        $writer->close();

        $this->asOwner()->post(route('dashboard.products.import.store'), ['file' => new UploadedFile($path, 'products.xlsx', null, null, true)])->assertSessionHasNoErrors();
        $this->assertSame(250, MenuItem::where('barcode', '6151100000048')->sole()->base_price);
    }

    public function test_the_template_downloads_and_the_command_imports(): void
    {
        $response = $this->asOwner()->get(route('dashboard.products.import.template'))->assertOk();
        $this->assertStringContainsString('Name,Category,Price,SKU,Barcode', $response->streamedContent());

        $path = tempnam(sys_get_temp_dir(), 'products').'.csv';
        file_put_contents($path, "Name,Category,Price\nZinc tablets,Vitamins,3.00\n");
        $this->artisan('expresspos:import-products', ['file' => $path])->expectsOutput('1 new products, 0 updated, 0 pack sizes.')->assertSuccessful();
    }

    public function test_a_restaurant_has_none_of_it(): void
    {
        app(BusinessType::class)->set(BusinessType::RESTAURANT);
        $item = MenuItem::create(['category_id' => $this->category->id, 'name' => 'Wrap', 'slug' => 'wrap', 'base_price' => 5000]);

        $this->asOwner()->get(route('dashboard.products.import'))->assertNotFound();
        $this->asOwner()->post(route('dashboard.menu-items.packs.store', $item), ['pack_name' => 'Box', 'pack_units' => 2, 'pack_price' => '9'])->assertNotFound();
        $this->asOwner()->get(route('dashboard.menu-items.edit', $item))->assertOk()->assertDontSee('Pack sizes')->assertDontSee('Product code (SKU');
        $this->asOwner()->get(route('dashboard.menu-items.index'))->assertOk()->assertDontSee('Search by name, product code or barcode');

        // Product fields sent anyway are ignored.
        $this->asOwner()->post(route('dashboard.menu-items.store'), $this->product(['name' => 'Kebab', 'sku' => 'K1', 'barcode' => '123']));
        $this->assertNull(MenuItem::where('name', 'Kebab')->sole()->sku);
    }
}
