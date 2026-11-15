<?php

namespace Tests\Feature;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 3a: module switches, and the seven modules switchable
 * so far — a switched-off module disappears completely (CLAUDE.md
 * product rule 3) and comes back when switched on.
 */
class ModulesTest extends TestCase
{
    use RefreshDatabase;

    private const STEP_3A = [
        ModuleService::BOLT_FOOD, ModuleService::WHATSAPP_REWARD, ModuleService::FREEBIES,
        ModuleService::MARKET_LIST, ModuleService::STOCK, ModuleService::REFUNDS, ModuleService::REVIEWS,
    ];

    private Branch $branch;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Wraps', 'slug' => 'wraps'])->id,
            'name' => 'Chicken Wrap', 'slug' => 'chicken-wrap', 'base_price' => 3000,
        ]);
        $this->branch->menuItems()->attach($this->item->id, ['is_available' => true]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function switchOff(string ...$modules): void
    {
        foreach ($modules as $module) {
            app(ModuleService::class)->set($module, false);
        }
    }

    private function order(): Order
    {
        $order = Order::create([
            'reference' => 'POS-1', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 3000, 'total' => 3000,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'channel' => 'pos',
        ]);
        $order->forceFill(['status' => 'completed', 'placed_at' => now()])->save();

        return $order;
    }

    public function test_only_the_owner_switches_modules(): void
    {
        $this->as($this->userWithRole('general_manager'))->get(route('dashboard.modules.edit'))->assertForbidden();
        $this->as($this->userWithRole('manager'))->put(route('dashboard.modules.update'), ['modules' => ['freebies' => '0']])->assertForbidden();

        $owner = $this->userWithRole('owner');
        $this->as($owner)->get(route('dashboard.modules.edit'))->assertOk()->assertSee('Market list and profit')->assertSee('its switch comes in a later update');

        $this->as($owner)->put(route('dashboard.modules.update'), [
            'modules' => [...array_fill_keys(self::STEP_3A, '1'), 'freebies' => '0', 'website' => '0'],
        ])->assertSessionHas('status');

        $this->assertSame('0', Setting::where('key', 'module_freebies')->value('value'));
        $this->assertSame('1', Setting::where('key', 'module_stock')->value('value'));
        // A switch that isn't built yet can't be changed.
        $this->assertNull(Setting::where('key', 'module_website')->value('value'));
    }

    public function test_a_new_install_starts_with_every_module_off(): void
    {
        config(['modules.default_on' => false]);
        $modules = new ModuleService;

        foreach (self::STEP_3A as $module) {
            $this->assertFalse($modules->enabled($module), "{$module} should start off");
        }
        // Not switchable yet, so it behaves as before.
        $this->assertTrue($modules->enabled(ModuleService::WEBSITE));
    }

    public function test_the_migration_switches_every_module_on_only_for_an_install_with_orders(): void
    {
        $migration = require database_path('migrations/2026_11_15_130000_switch_on_every_module.php');

        $migration->up();
        $this->assertSame(0, Setting::where('key', 'like', 'module%')->count());

        $this->order();
        Setting::create(['key' => 'module_bolt_food', 'value' => '0']);
        $migration->up();

        $this->assertSame(count(ModuleService::MODULES), Setting::where('key', 'like', 'module%')->count());
        $this->assertSame('1', Setting::where('key', 'module_market_list')->value('value'));
        $this->assertSame('0', Setting::where('key', 'module_bolt_food')->value('value'), 'an existing switch is never overwritten');
    }

    public function test_every_route_of_a_module_is_guarded_by_its_switch(): void
    {
        $expected = [
            'module:reviews' => ['reviews.index', 'tracking.review.store', 'dashboard.reviews.index', 'dashboard.reviews.approve'],
            'module:refunds' => ['dashboard.refunds.index', 'orders.refunds.store', 'rider.refunds.store', 'rider.refunds.complete', 'dashboard.damage-reports.index', 'dashboard.damage-reports.store', 'dashboard.damage-reports.photo'],
            'module:freebies' => ['dashboard.freebies.approve', 'dashboard.freebies.reject'],
            'module:freebies,whatsapp_reward' => ['dashboard.freebies.index'],
            'module:market_list' => ['dashboard.market-lists.index', 'dashboard.market-lists.kinds.index', 'dashboard.market-lists.receipt', 'dashboard.reports.expenses.index', 'dashboard.reports.profit.download'],
            'module:stock' => ['dashboard.stock.index', 'dashboard.stock.restock', 'dashboard.menu-items.recipe.store', 'dashboard.options.recipe.store', 'dashboard.recipe.destroy'],
        ];

        foreach ($expected as $middleware => $names) {
            foreach ($names as $name) {
                $this->assertContains($middleware, Route::getRoutes()->getByName($name)->gatherMiddleware(), "{$name} is missing {$middleware}");
            }
        }
    }

    public function test_switched_off_modules_disappear_and_come_back(): void
    {
        $owner = $this->userWithRole('owner');
        $staff = $this->userWithRole('staff');
        $order = $this->order();
        $this->switchOff(...self::STEP_3A);

        $settings = $this->as($owner)->get(route('dashboard.settings.index'))->assertOk();
        foreach (['dashboard.market-lists.index', 'dashboard.stock.index', 'dashboard.refunds.index', 'dashboard.damage-reports.index', 'dashboard.freebies.index', 'dashboard.reviews.index'] as $route) {
            $settings->assertDontSee(route($route), false);
        }
        $settings->assertDontSee('Bolt Food orders prefix')->assertDontSee('WhatsApp group reward')->assertDontSee('Freebies prefix');

        foreach (['dashboard.market-lists.index', 'dashboard.reports.profit.index', 'dashboard.stock.index', 'dashboard.refunds.index', 'dashboard.freebies.index', 'dashboard.reviews.index'] as $route) {
            $this->as($owner)->get(route($route))->assertNotFound();
        }

        $this->as($owner)->get(route('dashboard.reports.today.index'))->assertOk()
            ->assertDontSee(route('dashboard.reports.profit.index'), false)
            ->assertDontSee('channel=bolt_food', false);
        $this->as($staff)->get(route('dashboard.pos.index'))->assertOk()
            ->assertDontSee('value="bolt_food"', false)
            ->assertDontSee('value="complimentary"', false)
            ->assertDontSee('WhatsApp group order');
        $this->as($owner)->get(route('dashboard.menu-items.edit', $this->item))->assertOk()->assertDontSee('Recipe (stock used)');
        $this->as($owner)->get(route('dashboard.orders.show', $order))->assertOk()->assertDontSee(route('orders.refunds.store', $order), false);

        $this->get(route('reviews.index'))->assertNotFound();
        $this->get(route('home'))->assertDontSee(route('reviews.index'), false);
        $this->get(route('sitemap'))->assertDontSee(route('reviews.index'), false);
        $this->get(route('tracking.show', $order))->assertOk()->assertDontSee('Submit review');

        // Saving Settings still works without the hidden fields.
        $this->as($owner)->put(route('dashboard.settings.update'), [
            'order_reference_prefix_pos' => 'POS', 'order_reference_prefix_web' => 'WEB',
        ])->assertSessionHasNoErrors();

        // Switched back on, it all returns.
        $this->as($owner)->put(route('dashboard.modules.update'), ['modules' => array_fill_keys(self::STEP_3A, '1')]);

        $settings = $this->as($owner)->get(route('dashboard.settings.index'));
        foreach (['dashboard.market-lists.index', 'dashboard.stock.index', 'dashboard.refunds.index', 'dashboard.freebies.index', 'dashboard.reviews.index'] as $route) {
            $settings->assertSee(route($route), false);
            $this->as($owner)->get(route($route))->assertOk();
        }
        $this->as($staff)->get(route('dashboard.pos.index'))->assertSee('value="bolt_food"', false)->assertSee('value="complimentary"', false);
    }

    public function test_the_pos_and_the_order_service_refuse_switched_off_options(): void
    {
        $this->switchOff(ModuleService::BOLT_FOOD, ModuleService::FREEBIES, ModuleService::WHATSAPP_REWARD);
        $staff = $this->userWithRole('staff');

        foreach ([
            ['payment_method' => 'bolt_food'],
            ['payment_method' => 'complimentary', 'freebie_recipient_type' => 'customer', 'freebie_reason' => 'Birthday', 'name' => 'Ama'],
            ['payment_method' => 'cash', 'source' => 'whatsapp'],
        ] as $fields) {
            $this->as($staff)->postJson(route('dashboard.pos.cart.add'), ['menu_item_id' => $this->item->id, 'quantity' => 1, 'option_ids' => []])->assertOk();
            $this->as($staff)->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', ...$fields])->assertUnprocessable();
        }

        $this->expectException(OrderPlacementException::class);
        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'bolt_food',
            items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 1)],
        ));
    }

    public function test_the_freebies_page_shows_only_what_is_switched_on(): void
    {
        $owner = $this->userWithRole('owner');
        $this->switchOff(ModuleService::FREEBIES);

        $this->as($owner)->get(route('dashboard.freebies.index'))->assertOk()
            ->assertSee('WhatsApp group rewards')
            ->assertDontSee('Freebies given');
        $this->as($owner)->get(route('dashboard.settings.index'))->assertSee('WhatsApp rewards');

        $this->switchOff(ModuleService::WHATSAPP_REWARD);
        $this->as($owner)->get(route('dashboard.freebies.index'))->assertNotFound();
    }

    public function test_bolt_food_report_views_fall_back_to_sales_when_switched_off(): void
    {
        $owner = $this->userWithRole('owner');
        $this->switchOff(ModuleService::BOLT_FOOD);

        $this->as($owner)->get(route('dashboard.reports.today.index', ['channel' => 'bolt_food']))->assertOk()->assertViewHas('channel', 'pos');
        $this->as($owner)->get(route('dashboard.reports.invoices.index', ['type' => 'bolt_food']))->assertOk()->assertViewHas('type', 'sales');

        $csv = $this->as($owner)->get(route('dashboard.reports.profit.download', ['format' => 'csv']))->assertOk()->getContent();
        $this->assertStringContainsString('Profit (ghs)', $csv);
        $this->assertStringNotContainsString('Bolt Food', $csv);
    }
}
