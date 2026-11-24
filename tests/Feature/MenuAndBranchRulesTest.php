<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use App\Services\Promotions\PromotionService;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 2b: drinks, the buy-X-get-Y free item, a branch's areas
 * and the home page's strips are data each business sets, not code.
 */
class MenuAndBranchRulesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole('owner');

        return $user;
    }

    private function item(Category $category, string $name, int $price = 3000): MenuItem
    {
        $item = MenuItem::create(['category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug(), 'base_price' => $price]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => true]);

        return $item;
    }

    private function row(MenuItem $item, int $quantity): array
    {
        return ['menu_item_id' => $item->id, 'name_snapshot' => $item->name, 'unit_price_snapshot' => $item->base_price, 'quantity' => $quantity, 'line_total' => $item->base_price * $quantity];
    }

    public function test_any_category_marked_as_drinks_is_treated_as_drinks(): void
    {
        $food = Category::create(['name' => 'Waakye', 'slug' => 'waakye']);
        $juices = Category::create(['name' => 'Fresh Juices', 'slug' => 'fresh-juices', 'is_beverage' => true]);
        $waakye = $this->item($food, 'Waakye Special');
        $sobolo = $this->item($juices, 'Sobolo', 1000);
        $promotion = Promotion::create(['code' => 'B2G1', 'type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 2, 'free_quantity' => 1, 'free_menu_item_id' => $waakye->id, 'is_active' => true]);

        // Four juices earn nothing.
        $this->assertCount(1, app(PromotionService::class)->appendFreeLines($promotion, [$this->row($sobolo, 4)]));

        // The juice is suggested on a food item's page, and sits side by side on the menu.
        $this->get(route('menu.show', $waakye))->assertOk()->assertSee('Sobolo');
        $this->assertContains('fresh-juices', $this->get(route('menu.index'))->viewData('sideBySideCategories'));
    }

    public function test_each_promotion_gives_away_its_own_free_item(): void
    {
        $food = Category::create(['name' => 'Burgers', 'slug' => 'burgers']);
        $burger = $this->item($food, 'Beef Burger', 6000);
        $fries = $this->item($food, 'Plain Fries', 1500);
        $promotion = Promotion::create(['code' => 'B2G1', 'type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 2, 'free_quantity' => 1, 'free_menu_item_id' => $fries->id, 'is_active' => true]);

        $lines = app(PromotionService::class)->appendFreeLines($promotion, [$this->row($burger, 2)]);

        $this->assertSame($fries->id, end($lines)['menu_item_id']);
        $this->assertSame(0, end($lines)['line_total']);
    }

    public function test_a_promotion_without_a_free_item_gives_nothing_away(): void
    {
        $burger = $this->item(Category::create(['name' => 'Burgers', 'slug' => 'burgers']), 'Beef Burger');
        $promotion = Promotion::create(['code' => 'B2G1', 'type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 2, 'free_quantity' => 1, 'is_active' => true]);

        $this->assertCount(1, app(PromotionService::class)->appendFreeLines($promotion, [$this->row($burger, 2)]));
    }

    public function test_the_dashboard_saves_drinks_areas_and_the_free_item(): void
    {
        $owner = $this->owner();
        $session = ['current_branch_id' => $this->branch->id];

        $this->actingAs($owner)->withSession($session)->post(route('dashboard.categories.store'), ['name' => 'Juices', 'is_active' => '1', 'is_beverage' => '1']);
        $this->assertTrue(Category::where('name', 'Juices')->first()->is_beverage);

        $this->actingAs($owner)->withSession($session)->put(route('dashboard.branches.update', $this->branch), [
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A', 'lat' => 5.5, 'lng' => -0.1,
            'opens_at' => '10:00', 'closes_at' => '22:00', 'is_active' => '1',
            'areas_served' => "Osu, Labone,\nCantonments, , Osu",
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Osu', 'Labone', 'Cantonments'], $this->branch->refresh()->areas_served);

        $promo = ['code' => 'B2G1', 'type' => 'buy_x_get_y_free', 'buy_quantity' => 2, 'free_quantity' => 1, 'is_active' => '1', 'is_automatic' => '1', 'recurring_days' => [3]];
        $this->actingAs($owner)->withSession($session)->post(route('dashboard.promotions.store'), $promo)->assertSessionHasErrors('free_menu_item_id');

        $fries = $this->item(Category::where('name', 'Juices')->first(), 'Plain Fries');
        $this->actingAs($owner)->withSession($session)->post(route('dashboard.promotions.store'), [...$promo, 'free_menu_item_id' => $fries->id])->assertSessionHasNoErrors();
        $this->assertSame($fries->id, Promotion::where('code', 'B2G1')->value('free_menu_item_id'));
    }

    public function test_home_strips_come_from_the_setting_or_one_per_category(): void
    {
        $wraps = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $sides = Category::create(['name' => 'Sides', 'slug' => 'sides']);
        $this->item($wraps, 'Chicken Wrap');
        $this->item($sides, 'Kelewele');

        $this->get(route('home'))->assertSee('text-brand-on-surface">Wraps</h2>', false)->assertSee('text-brand-on-surface">Sides</h2>', false);

        app(SettingsService::class)->set(SettingsService::HOME_MENU_SLIDERS, json_encode([['title' => 'Everything good', 'slugs' => ['wraps', 'sides']]]));

        $this->get(route('home'))->assertSee('text-brand-on-surface">Everything good</h2>', false)->assertDontSee('text-brand-on-surface">Wraps</h2>', false);
    }

    public function test_the_backfill_copies_yes_my_shawarmas_rules(): void
    {
        $migration = require database_path('migrations/2026_11_15_110000_add_menu_and_branch_rules.php');
        $migration->down();

        $drinks = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $chicken = MenuItem::create(['category_id' => $drinks->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $pokuase = Branch::create([
            'name' => 'Pokuase Y-Junction', 'slug' => 'pokuase-y-junction', 'phone' => '+233241113333', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        Order::create([
            'reference' => 'X-1', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 100, 'total' => 100,
            'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);
        $promoId = DB::table('promotions')->insertGetId(['code' => 'WED', 'type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 2, 'free_quantity' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();

        $this->assertTrue($drinks->refresh()->is_beverage);
        $this->assertSame($chicken->id, Promotion::find($promoId)->free_menu_item_id);
        $this->assertNotEmpty($pokuase->refresh()->areas_served);
        $this->assertSame('Shawarma favourites', json_decode(Setting::where('key', 'home_menu_sliders')->value('value'), true)[0]['title']);
    }
}
