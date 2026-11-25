<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Delivery\DeliveryFeeCalculator;
use App\Services\Modules\ModuleService;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The last business amounts out of the code (delivery fees, owner
 * 2026-11-15) and the home page strip editor.
 */
class DeliveryFeesAndHomeStripsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
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

    private function settings(array $overrides = []): array
    {
        return ['order_reference_prefix_pos' => 'POS', 'order_reference_prefix_web' => 'WEB', 'order_reference_prefix_bolt_food' => 'BOLT',
            'order_reference_prefix_whatsapp' => 'WA', 'order_reference_prefix_freebie' => 'FREE', 'whatsapp_reward' => '10', ...$overrides];
    }

    public function test_the_business_sets_its_own_delivery_fees(): void
    {
        $calculator = app(DeliveryFeeCalculator::class);
        $this->assertSame([500, 1000, 2000], [$calculator->ratePerKm(), $calculator->minimumFee(), $calculator->minimumOrderTotal()]);

        $owner = $this->userWithRole('owner');
        $this->as($owner)->get(route('dashboard.settings.index'))->assertSee('Delivery fees')->assertSee('value="5.00"', false);

        $this->as($owner)->put(route('dashboard.settings.update'), $this->settings([
            'delivery_rate_per_km' => '7.50', 'delivery_minimum_fee' => '15', 'delivery_minimum_order' => '50',
        ]))->assertSessionHasNoErrors();

        $calculator = app(DeliveryFeeCalculator::class);
        $this->assertSame([750, 1500, 5000], [$calculator->ratePerKm(), $calculator->minimumFee(), $calculator->minimumOrderTotal()]);
        // Right next to the branch: the minimum fee.
        $this->assertSame(1500, $calculator->calculate($this->branch, 5.5, -0.1));
        // About 11 km away: rate × distance, rounded to whole cedis.
        $this->assertSame((int) round($calculator->distanceKm($this->branch, 5.6, -0.1) * 750 / 100) * 100, $calculator->calculate($this->branch, 5.6, -0.1));
    }

    public function test_delivery_fees_are_hidden_and_kept_while_delivery_is_off(): void
    {
        Setting::create(['key' => SettingsService::DELIVERY_RATE_PER_KM, 'value' => '800']);
        app(ModuleService::class)->set(ModuleService::DELIVERY, false);
        $owner = $this->userWithRole('owner');

        $this->as($owner)->get(route('dashboard.settings.index'))->assertDontSee('Delivery fees');
        $this->as($owner)->put(route('dashboard.settings.update'), $this->settings(['delivery_rate_per_km' => '1']))->assertSessionHasNoErrors();

        $this->assertSame('800', Setting::where('key', SettingsService::DELIVERY_RATE_PER_KM)->value('value'));
    }

    public function test_the_backfill_stores_yes_my_shawarmas_delivery_fees_only_with_orders(): void
    {
        $migration = require database_path('migrations/2026_11_15_150000_fill_delivery_fee_settings.php');

        $migration->up();
        $this->assertNull(Setting::where('key', SettingsService::DELIVERY_RATE_PER_KM)->value('value'));

        Order::create([
            'reference' => 'X-1', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 100, 'total' => 100,
            'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);
        Setting::create(['key' => SettingsService::DELIVERY_MINIMUM_FEE, 'value' => '1200']);
        $migration->up();

        $this->assertSame('500', Setting::where('key', SettingsService::DELIVERY_RATE_PER_KM)->value('value'));
        $this->assertSame('2000', Setting::where('key', SettingsService::DELIVERY_MINIMUM_ORDER)->value('value'));
        $this->assertSame('1200', Setting::where('key', SettingsService::DELIVERY_MINIMUM_FEE)->value('value'), 'an existing setting is never overwritten');
    }

    public function test_the_home_page_strips_are_edited_on_website_content(): void
    {
        $wraps = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $sides = Category::create(['name' => 'Sides', 'slug' => 'sides']);
        foreach ([[$wraps, 'Chicken Wrap'], [$sides, 'Kelewele']] as [$category, $name]) {
            $item = MenuItem::create(['category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug(), 'base_price' => 2000]);
            $this->branch->menuItems()->attach($item->id, ['is_available' => true]);
        }

        $this->as($this->userWithRole('manager'))->get(route('dashboard.home-strips.edit'))->assertForbidden();

        $gm = $this->userWithRole('general_manager');
        $this->as($gm)->get(route('dashboard.home-strips.edit'))->assertOk()->assertSee('one strip per category');

        $this->as($gm)->put(route('dashboard.home-strips.update'), ['strips' => [['title' => '', 'slugs' => []]]])
            ->assertSessionHasErrors(['strips.0.title', 'strips.0.slugs']);

        $this->as($gm)->put(route('dashboard.home-strips.update'), ['strips' => [
            ['title' => 'Everything good', 'slugs' => ['sides', 'wraps']],
        ]])->assertSessionHas('status');

        $this->get(route('home'))->assertSee('text-brand-on-surface">Everything good</h2>', false)->assertDontSee('text-brand-on-surface">Wraps</h2>', false);

        $this->as($gm)->put(route('dashboard.home-strips.update'), ['reset' => '1'])->assertSessionHas('status');
        $this->get(route('home'))->assertSee('text-brand-on-surface">Wraps</h2>', false)->assertSee('text-brand-on-surface">Sides</h2>', false);

        app(ModuleService::class)->set(ModuleService::WEBSITE, false);
        $this->as($gm)->get(route('dashboard.home-strips.edit'))->assertNotFound();
    }

    /**
     * Sentry PHP-LARAVEL-11: every strip ran its own category, item and
     * availability queries. Now three queries in all, whatever the number
     * of strips, with each strip still getting exactly its own items in
     * sort order.
     */
    public function test_home_strips_load_their_items_in_one_pass(): void
    {
        // Created out of order, so the strips' order can only come from sort_order.
        foreach (['wraps' => ['Beef Wrap' => 2, 'Chicken Wrap' => 1], 'sides' => ['Kelewele' => 3], 'drinks' => ['Asaana' => 2, 'Sobolo' => 1]] as $slug => $names) {
            $category = Category::create(['name' => ucfirst($slug), 'slug' => $slug]);
            foreach ($names as $name => $sortOrder) {
                $item = MenuItem::create(['category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug(), 'base_price' => 2000, 'sort_order' => $sortOrder]);
                $this->branch->menuItems()->attach($item->id, ['is_available' => $name !== 'Asaana']);
            }
        }
        $gm = $this->userWithRole('general_manager');
        $this->as($gm)->put(route('dashboard.home-strips.update'), ['strips' => [
            ['title' => 'Wraps and sides', 'slugs' => ['wraps', 'sides']],
            ['title' => 'Cold drinks', 'slugs' => ['drinks']],
        ]])->assertSessionHas('status');
        auth()->logout();
        $this->get(route('branches.pick', $this->branch));

        $categoryQueries = 0;
        DB::listen(function ($query) use (&$categoryQueries) {
            if (str_contains($query->sql, 'from "categories" where "slug" in')) {
                $categoryQueries++;
            }
        });

        $response = $this->get(route('home'))->assertOk();

        $this->assertSame(1, $categoryQueries);
        $strips = collect($response->viewData('menuSliders'))->mapWithKeys(fn ($strip) => [$strip['title'] => $strip['items']->pluck('name')->all()]);
        $this->assertSame(['Wraps and sides', 'Cold drinks'], $strips->keys()->all());
        $this->assertSame(['Chicken Wrap', 'Beef Wrap', 'Kelewele'], $strips['Wraps and sides']);
        $this->assertSame(['Sobolo', 'Asaana'], $strips['Cold drinks']);
        // Sold out at this branch: still on the strip, greyed out.
        $asaana = collect($response->viewData('menuSliders'))->firstWhere('title', 'Cold drinks')['items']->firstWhere('name', 'Asaana');
        $this->assertFalse($asaana->isAvailable);
    }
}
