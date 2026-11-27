<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\FaqItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Printer;
use App\Models\User;
use App\Services\Modules\ModuleService;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\OrderStateMachine;
use App\Services\Seo\RestaurantSchemaBuilder;
use App\Services\Settings\BusinessType;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Restaurant, pharmacy or shop (owner, 2026-11-25; "Pharmacy and retail
 * plan", step R1). A restaurant is exactly as before; a pharmacy or shop
 * has no kitchen, no restaurant-only modules, and its own words.
 */
class BusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $item;

    private User $staff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $category = Category::create(['name' => 'Pain relief', 'slug' => 'pain-relief']);
        $this->item = MenuItem::create(['category_id' => $category->id, 'name' => 'Paracetamol 500mg', 'slug' => 'paracetamol', 'base_price' => 1500]);
        $this->branch->menuItems()->attach($this->item->id, ['is_available' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->staff = User::factory()->create();
        $this->staff->assignRole('staff');
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function sale(string $channel = 'pos', string $fulfilment = 'pickup'): Order
    {
        return app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->branch->id,
            fulfilmentType: $fulfilment, paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 1, optionQuantities: [])],
            channel: $channel,
            actorType: $channel === 'pos' ? 'staff' : null,
            actorId: $channel === 'pos' ? $this->staff->id : null,
        ));
    }

    public function test_an_install_without_a_type_is_a_restaurant_exactly_as_before(): void
    {
        $type = app(BusinessType::class);
        $this->assertSame(BusinessType::RESTAURANT, $type->current());
        $this->assertTrue(app(ModuleService::class)->enabled(ModuleService::TABLES));
        $this->assertSame('paid', $this->sale()->status, 'the kitchen still accepts it');

        $this->asOwner()->get(route('dashboard.menu-items.index'))->assertOk()->assertSee('Menu')->assertSee('Modifiers');
        $this->asOwner()->get(route('dashboard.option-groups.index'))->assertOk();
        $this->assertSame('Restaurant', app(RestaurantSchemaBuilder::class)->forBranch($this->branch)['@type']);
    }

    public function test_a_pharmacy_has_no_restaurant_only_modules(): void
    {
        app(BusinessType::class)->set(BusinessType::PHARMACY);
        $modules = app(ModuleService::class);

        foreach (BusinessType::RESTAURANT_ONLY_MODULES as $module) {
            $this->assertFalse($modules->enabled($module), "{$module} is off for a pharmacy");
        }
        $this->assertTrue($modules->enabled(ModuleService::WEBSITE));
        $this->assertTrue($modules->enabled(ModuleService::DELIVERY));

        $this->asOwner()->get(route('dashboard.modules.edit'))->assertOk()
            ->assertSee('Delivery and riders')->assertDontSee('Bolt Food')->assertDontSee('Tables');

        // Switching one on from a forged form does nothing.
        $this->asOwner()->put(route('dashboard.modules.update'), ['modules' => ['tables' => '1', 'website' => '1']]);
        $this->assertFalse($modules->enabled(ModuleService::TABLES));
    }

    public function test_a_pharmacy_has_no_kitchen_screens_modifiers_or_time_table(): void
    {
        app(BusinessType::class)->set(BusinessType::PHARMACY);

        $this->asOwner()->get(route('dashboard.option-groups.index'))->assertNotFound();
        $this->asOwner()->get(route('dashboard.menu-items.timetable'))->assertNotFound();
        $this->asOwner()->get(route('dashboard.menu-items.index'))->assertOk()->assertDontSee('Modifiers')->assertDontSee('Time table');
        $this->asOwner()->get(route('dashboard.branches.edit', $this->branch))->assertOk()->assertDontSee('This branch has a kitchen screen');
        $this->asOwner()->get(route('dashboard.business-profile.edit'))->assertOk()->assertDontSee('What you serve');
    }

    public function test_a_pharmacy_has_no_kitchen_printers_or_tickets(): void
    {
        app(BusinessType::class)->set(BusinessType::PHARMACY);

        $this->asOwner()->get(route('dashboard.printers.index'))->assertOk()->assertDontSee('Kitchen tickets');
        $this->asOwner()->post(route('dashboard.printers.store'), ['name' => 'Back', 'role' => 'kitchen', 'paper_width' => 80, 'connection' => 'network', 'address' => '192.168.1.20'])
            ->assertSessionHasErrors('role');

        // One left over from before can't print kitchen tickets either.
        Printer::create(['branch_id' => $this->branch->id, 'name' => 'Old', 'role' => Printer::KITCHEN, 'paper_width' => 80, 'connection' => 'network', 'address' => '192.168.1.21', 'is_active' => true]);
        $order = $this->sale();
        $this->asOwner()->get(route('dashboard.orders.print', [$order, 'kitchen']))->assertNotFound();
    }

    public function test_a_counter_sale_completes_as_it_is_paid(): void
    {
        app(BusinessType::class)->set(BusinessType::SHOP);

        $order = $this->sale();

        $this->assertSame('dispatched', $order->status);
        $this->assertSame(
            ['placed', 'no_kitchen_screen', 'no_kitchen_screen', 'counter_sale', 'counter_sale'],
            $order->events()->orderBy('id')->get()->map(fn ($e) => $e->meta['action'] ?? null)->all(),
            'every step is a transition with its own order_events row',
        );
    }

    public function test_website_and_delivery_orders_are_still_worked_on_the_board(): void
    {
        app(BusinessType::class)->set(BusinessType::PHARMACY);
        $machine = app(OrderStateMachine::class);

        $web = $this->sale(channel: 'web');
        $this->assertSame('paid', $web->status, 'waiting to be accepted');
        $machine->transition($web, 'accepted', 'staff', $this->staff->id);
        $this->assertSame('preparing', $web->fresh()->status);
        $machine->transition($web->fresh(), 'ready', 'staff', $this->staff->id);
        $this->assertSame('ready', $web->fresh()->status, 'the customer still collects it');
    }

    public function test_a_pharmacy_reads_its_own_words(): void
    {
        app(BusinessType::class)->set(BusinessType::PHARMACY);

        $this->assertSame('Products', __('Menu Editor'));
        $this->asOwner()->get(route('dashboard.menu-items.index'))->assertOk()->assertSee('Products')->assertDontSee('Menu Editor');
        $this->assertSame('Pharmacy', app(RestaurantSchemaBuilder::class)->forBranch($this->branch)['@type']);
        $this->assertArrayNotHasKey('hasMenu', app(RestaurantSchemaBuilder::class)->forBranch($this->branch));
    }

    /** A word list whose line no longer matches the app's English would quietly stop working. */
    public function test_every_word_list_line_matches_a_line_in_the_app(): void
    {
        $code = collect(File::allFiles(resource_path('views')))->merge(File::allFiles(app_path()))
            ->map(fn ($file) => str_replace("\\'", "'", $file->getContents()))->implode("\n");

        foreach (array_keys(BusinessType::TYPES) as $type) {
            if ($type === BusinessType::RESTAURANT) {
                continue;
            }
            foreach (array_keys(json_decode(File::get(lang_path("business-types/{$type}/en.json")), true)) as $line) {
                $this->assertStringContainsString($line, $code, "{$type}: \"{$line}\" isn't in the app any more");
            }
        }
    }

    public function test_onboarding_sets_the_type_once_and_refuses_restaurant_only_modules(): void
    {
        Order::query()->delete();
        $this->owner->delete();
        $this->staff->delete();
        $this->branch->menuItems()->detach();
        $this->branch->delete();

        $logo = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng(imagecreatetruecolor(800, 240), $logo);
        $options = [
            '--business' => 'Good Health Pharmacy', '--type' => 'pharmacy', '--branch' => 'Madina', '--branch-phone' => '+233241234567',
            '--address' => 'Madina Market Road', '--lat' => '5.67', '--lng' => '-0.17', '--owner-name' => 'Esi', '--owner-email' => 'esi@example.test',
            '--primary-colour' => '#0b7a3a', '--accent-colour' => '#fcd116', '--background-colour' => '#ffffff', '--logo' => $logo,
            '--no-interaction' => true,
        ];

        $this->artisan('expresspos:new-client', [...$options, '--modules' => 'website,tables'])->assertFailed();
        $this->assertSame(BusinessType::RESTAURANT, app(BusinessType::class)->current(), 'nothing set up');

        $this->artisan('expresspos:new-client', [...$options, '--modules' => 'website,delivery'])->assertSuccessful();
        $this->assertSame(BusinessType::PHARMACY, app(SettingsService::class)->get(BusinessType::SETTING));
        $this->assertTrue(FaqItem::where('question', 'Can I order prescription medicines online?')->exists(), 'the pharmacy starter text');
    }
}
