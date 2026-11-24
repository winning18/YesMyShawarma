<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The shared staging demo and client sandboxes (owner, 2026-11-24).
 */
class DemoAndSandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_fills_an_empty_install_with_a_made_up_restaurant(): void
    {
        Storage::fake('public');

        $this->artisan('expresspos:demo-data')->assertSuccessful()->expectsOutputToContain('Express POS Demo is ready.');

        $this->assertSame('Express POS Demo', app(BusinessProfile::class)->name());
        $this->assertSame(['Osu', 'East Legon'], Branch::orderBy('id')->pluck('name')->all());
        $this->assertSame(4, Category::count());
        $this->assertSame(11, MenuItem::count());
        $this->assertSame(11, Branch::firstWhere('name', 'East Legon')->menuItems()->wherePivot('is_available', true)->count(), 'the whole menu is on sale at both branches');
        $this->assertSame(8, DiningTable::count());
        $this->assertSame(6, User::count(), 'one account per role');
        $this->assertNotNull(User::firstWhere('email', 'cashier@demo.expresspos.test')->pin);
        $this->assertSame('#111827', app(BusinessProfile::class)->colours()['brand_background']);
        $this->assertNotNull(app(BusinessProfile::class)->logoUrl());

        // Only on an empty install, unless --fresh.
        $this->artisan('expresspos:demo-data')->assertFailed();
    }

    public function test_the_demo_never_goes_on_a_live_install(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('expresspos:demo-data')->assertFailed();
        $this->assertSame(0, Branch::count());
    }

    public function test_a_sandbox_copy_has_no_real_customer_data_left(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $customer = Customer::create(['phone' => '+233244556677', 'name' => 'Ama Mensah', 'email' => 'ama@real.example', 'password' => bcrypt('secret-pass')]);
        DB::table('customer_addresses')->insert(['customer_id' => $customer->id, 'label' => 'Home', 'landmark' => 'Blue gate near Ama\'s church', 'ghanapost_code' => 'GA-123-4567', 'lat' => 5.5612345, 'lng' => -0.1823456, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
        $order = Order::create([
            'reference' => 'X-1', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => $customer->id,
            'branch_id' => $branch->id, 'fulfilment_type' => 'delivery', 'subtotal' => 100, 'total' => 100,
            'payment_method' => 'cash', 'payment_status' => 'paid',
            'delivery_address_snapshot' => ['area_name' => 'Osu', 'landmark' => 'Blue gate', 'ghanapost_code' => 'GA-123-4567', 'lat' => 5.5612345, 'lng' => -0.1823456],
        ]);
        DB::table('newsletter_subscribers')->insert(['email' => 'ama@real.example', 'subscribed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        app(SettingsService::class)->set(SettingsService::WEBSITE_DOMAIN, 'deboyeskitchen.com');
        $staff = User::factory()->create(['email' => 'owner@client.example']);

        $this->artisan('expresspos:scrub-sandbox')->assertSuccessful();

        $customer->refresh();
        $this->assertSame('Customer '.$customer->id, $customer->name);
        $this->assertStringStartsWith('+2339', $customer->phone);
        $this->assertNotSame('ama@real.example', $customer->email);
        $this->assertNull($customer->getAttributes()['password']);

        $address = DB::table('customer_addresses')->first();
        $this->assertStringNotContainsString('Ama', $address->landmark);
        $this->assertNull($address->ghanapost_code);
        $this->assertEqualsWithDelta(5.56, (float) $address->lat, 0.0001, 'blurred to about a kilometre');

        $snapshot = $order->fresh()->delivery_address_snapshot;
        $this->assertSame('Osu', $snapshot['area_name']);
        $this->assertSame('Landmark', $snapshot['landmark']);
        $this->assertNull($snapshot['ghanapost_code']);
        $this->assertEqualsWithDelta(5.56, $snapshot['lat'], 0.0001);

        $this->assertSame(0, DB::table('newsletter_subscribers')->count());
        $this->assertNull(app(SettingsService::class)->websiteDomain(), 'never answers on the client\'s own domain');
        $this->assertSame('owner@client.example', $staff->fresh()->email, 'staff accounts stay, so the owner can sign in');
    }

    public function test_scrubbing_is_refused_on_a_live_install(): void
    {
        $customer = Customer::create(['phone' => '+233244556677', 'name' => 'Ama Mensah']);
        $this->app['env'] = 'production';

        $this->artisan('expresspos:scrub-sandbox')->assertFailed();
        $this->assertSame('Ama Mensah', $customer->fresh()->name);
    }

    public function test_an_install_describes_itself_for_express_pos_admin(): void
    {
        Storage::fake('public');
        $this->artisan('expresspos:demo-data')->assertSuccessful();

        $this->assertSame(0, Artisan::call('expresspos:describe'));
        $described = json_decode(trim(Artisan::output()), true);

        $this->assertSame('Express POS Demo', $described['name']);
        $this->assertSame('Osu', $described['branch']['name']);
        $this->assertSame('owner@demo.expresspos.test', $described['owner']['email']);
        $this->assertContains('website', $described['modules']);
        $this->assertSame(['primary' => '#f59e0b', 'accent' => '#0f766e', 'background' => '#111827'], $described['colours']);
        $this->assertSame('classic', $described['theme']);
        $this->assertNotNull($described['logo']);

        // A removed owner is skipped for the next one still in use.
        $gm = User::firstWhere('email', 'gm@demo.expresspos.test');
        app(PermissionRegistrar::class)->setPermissionsTeamId(Branch::firstWhere('name', 'Osu')->id);
        $gm->assignRole('owner');
        User::firstWhere('email', 'owner@demo.expresspos.test')->delete();
        Artisan::call('expresspos:describe');
        $this->assertSame('gm@demo.expresspos.test', json_decode(trim(Artisan::output()), true)['owner']['email']);
    }
}
