<?php

namespace Tests\Feature;

use App\Exceptions\OrderPlacementException;
use App\Jobs\SendSms;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\SmsBroadcast;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Notifications\CustomerOrderNotifier;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Promotions\PromotionService;
use App\Services\Sms\SmsBroadcastService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    private const STEP_3B = [ModuleService::PROMOTIONS, ModuleService::SMS, ModuleService::PAYSTACK, ModuleService::DELIVERY];

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
        $this->as($owner)->get(route('dashboard.modules.edit'))->assertOk()->assertSee('Market list and profit')->assertSee('Website ordering');

        $this->as($owner)->put(route('dashboard.modules.update'), [
            'modules' => [...array_fill_keys(array_keys(ModuleService::MODULES), '1'), 'freebies' => '0', 'website' => '0'],
        ])->assertSessionHas('status');

        $this->assertSame('0', Setting::where('key', 'module_freebies')->value('value'));
        $this->assertSame('1', Setting::where('key', 'module_stock')->value('value'));
        $this->assertSame('0', Setting::where('key', 'module_website')->value('value'));
    }

    public function test_a_new_install_starts_with_every_module_off(): void
    {
        config(['modules.default_on' => false]);
        $modules = new ModuleService;

        foreach (array_keys(ModuleService::MODULES) as $module) {
            $this->assertFalse($modules->enabled($module), "{$module} should start off");
        }
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
        $this->as($owner)->put(route('dashboard.modules.update'), ['modules' => array_fill_keys(array_keys(ModuleService::MODULES), '1')]);

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

    public function test_the_paystack_switch_keeps_each_installs_current_setting(): void
    {
        $migration = require database_path('migrations/2026_11_15_140000_move_paystack_switch_to_modules.php');

        $migration->up();
        $this->assertNull(Setting::where('key', 'module_paystack')->value('value'), 'a new install stays unset (off)');

        Setting::create(['key' => 'paystack_enabled', 'value' => '1']);
        Setting::create(['key' => 'module_paystack', 'value' => '1']);
        $migration->up();
        $this->assertSame('1', Setting::where('key', 'module_paystack')->value('value'));
        $this->assertNull(Setting::where('key', 'paystack_enabled')->value('value'));

        // An install with orders whose Paystack was never switched on stays off.
        $this->order();
        Setting::where('key', 'module_paystack')->update(['value' => '1']);
        $migration->up();
        $this->assertSame('0', Setting::where('key', 'module_paystack')->value('value'));
    }

    public function test_with_promotions_off_there_are_no_offers_anywhere(): void
    {
        Promotion::create([
            'code' => 'EVERY-DAY', 'type' => 'percentage', 'value' => 10, 'is_active' => true,
            'is_automatic' => true, 'recurring_days' => [now('Africa/Accra')->dayOfWeek], 'banner_headline' => 'Ten percent off today',
        ]);
        Promotion::create(['code' => 'TENOFF', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
        $promotions = app(PromotionService::class);
        $this->assertNotNull($promotions->findActiveAutomatic($this->branch));

        $this->switchOff(ModuleService::PROMOTIONS);

        $this->assertNull($promotions->findActiveAutomatic($this->branch));
        $this->get(route('home'))->assertDontSee('Ten percent off today');
        $owner = $this->userWithRole('owner');
        $this->as($owner)->get(route('dashboard.promotions.index'))->assertNotFound();
        $this->as($owner)->get(route('dashboard.settings.index'))->assertDontSee(route('dashboard.promotions.index'), false);
        $this->postJson(route('checkout.apply-promo'), ['code' => 'TENOFF'])->assertNotFound();

        $order = app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->branch->id,
            fulfilmentType: 'pickup', paymentMethod: 'cash', items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 1)],
        ));
        $this->assertSame(0, $order->discount_total);

        $this->expectException(OrderPlacementException::class);
        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->branch->id,
            fulfilmentType: 'pickup', paymentMethod: 'cash', items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 1)],
            promoCode: 'TENOFF',
        ));
    }

    public function test_with_customer_sms_off_customers_get_no_messages_but_the_rest_still_works(): void
    {
        Queue::fake();
        $order = $this->order();

        app(CustomerOrderNotifier::class)->placed($order);
        Queue::assertPushed(SendSms::class, 1);

        $this->switchOff(ModuleService::SMS);
        app(CustomerOrderNotifier::class)->readyForPickup($order);
        Queue::assertPushed(SendSms::class, 1);
        $this->assertSame(0, app(SmsBroadcastService::class)->startDue());

        $owner = $this->userWithRole('owner');
        $this->as($owner)->get(route('dashboard.customers.broadcasts.index'))->assertNotFound();
        $broadcast = SmsBroadcast::create(['message' => 'Hi', 'status' => SmsBroadcast::STATUS_SENT, 'scheduled_at' => now(), 'failed_count' => 1, 'created_by' => $owner->id]);
        $this->as($owner)->post(route('dashboard.customers.broadcasts.retry', $broadcast))->assertNotFound();
        $this->as($owner)->post(route('dashboard.customers.broadcasts.resend', $broadcast))->assertNotFound();
        $this->as($owner)->getJson(route('dashboard.customers.broadcasts.audience', ['all' => 1]))->assertNotFound();
        $this->assertSame(SmsBroadcast::STATUS_SENT, $broadcast->fresh()->status);
        $this->as($owner)->get(route('dashboard.customers.index'))->assertOk()->assertDontSee(route('dashboard.customers.broadcasts.index'), false);
    }

    public function test_with_delivery_off_everything_is_pickup_and_the_rider_app_is_gone(): void
    {
        DeliveryArea::create(['name' => 'Labone']);
        $this->branch->update(['areas_served' => ['Labone', 'Osu']]);
        $this->switchOff(ModuleService::DELIVERY);
        $owner = $this->userWithRole('owner');
        $staff = $this->userWithRole('staff');

        $this->get('/rider/login')->assertNotFound();
        $this->as($owner)->get(route('dashboard.riders'))->assertNotFound();
        $this->as($owner)->get(route('dashboard.reports.delivery-fees.index'))->assertNotFound();
        $this->as($owner)->get(route('dashboard.reports.today.index'))->assertDontSee(route('dashboard.reports.delivery-fees.index'), false);
        $this->as($owner)->get(route('dashboard.users.create'))->assertOk()->assertViewHas('roles', fn (array $roles) => ! in_array('rider', $roles, true));

        $this->as($staff)->get(route('dashboard.pos.index'))->assertOk()->assertViewHas('deliveryAvailable', false);
        $this->as($staff)->postJson(route('dashboard.pos.cart.add'), ['menu_item_id' => $this->item->id, 'quantity' => 1, 'option_ids' => []])->assertOk();
        $this->as($staff)->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'delivery', 'payment_method' => 'cash'])->assertUnprocessable();

        $branchPage = $this->get(route('branches.show', $this->branch))->assertOk();
        $branchPage->assertDontSee('Delivery areas')->assertDontSee('areaServed', false);

        $customer = Customer::create(['phone' => '+233240000009', 'password' => 'secret-password']);
        $this->actingAs($customer, 'customer')->get(route('customer.account.addresses'))->assertNotFound();
        $this->actingAs($customer, 'customer')->get(route('customer.account.show'))->assertOk()->assertDontSee(route('customer.account.addresses'), false);

        $this->expectException(OrderPlacementException::class);
        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->branch->id,
            fulfilmentType: 'delivery', paymentMethod: 'cash', items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 1)],
        ));
    }

    public function test_with_the_website_off_the_address_opens_the_staff_login(): void
    {
        Queue::fake();
        $this->switchOff(ModuleService::WEBSITE);
        $owner = $this->userWithRole('owner');
        $order = $this->order();

        // POS-only: the address opens the staff login; the customer site is gone.
        $this->get(route('home'))->assertRedirect(route('login'));
        foreach (['menu.index', 'branches.index', 'contact', 'cart.show', 'checkout.show', 'customer.login', 'customer.register', 'sitemap', 'llms'] as $route) {
            $this->get(route($route))->assertNotFound();
        }
        $this->get(route('tracking.show', $order))->assertNotFound();
        $this->get(route('robots'))->assertSeeText('Disallow: /');

        // A "not found" page shows no links into the missing site.
        $notFound = $this->get('/menu')->getContent();
        $this->assertStringNotContainsString('See our menu', $notFound);
        $this->assertStringNotContainsString(route('cart.show'), $notFound);
        $this->assertStringContainsString(route('login'), $notFound);

        // The SMS opt-out link always works.
        $this->assertNotContains('module:website', Route::getRoutes()->getByName('sms.opt-out.show')->gatherMiddleware());

        // Paystack and Reviews need the website.
        $modules = app(ModuleService::class);
        $this->assertTrue($modules->switchedOn(ModuleService::REVIEWS));
        $this->assertFalse($modules->enabled(ModuleService::REVIEWS));
        $this->assertFalse($modules->enabled(ModuleService::PAYSTACK));

        // Staff side: no website pages, web history, Web report view or traffic.
        $settings = $this->as($owner)->get(route('dashboard.settings.index'))->assertOk();
        foreach (['dashboard.hero-slider.index', 'dashboard.site-pages.index', 'dashboard.staff-members.index'] as $route) {
            $settings->assertDontSee(route($route), false);
            $this->as($owner)->get(route($route))->assertNotFound();
        }
        $settings->assertDontSee(route('dashboard.orders.history', ['channel' => 'web']), false)->assertDontSee('Web orders prefix');
        $this->as($owner)->get(route('dashboard.reports.today.index', ['channel' => 'web']))->assertViewHas('channel', 'pos');
        $this->as($owner)->get(route('dashboard.performance', ['tab' => 'traffic']))->assertOk()->assertViewHas('tab', 'sales');
        $this->as($owner)->get(route('dashboard.modules.edit'))->assertSee('Needs Website ordering switched on');

        // Order updates by SMS still go, without a link to a tracking page that isn't there.
        app(CustomerOrderNotifier::class)->readyForPickup($order);
        Queue::assertPushed(SendSms::class, fn (SendSms $job) => ! str_contains($job->message, 'http'));
    }
}
