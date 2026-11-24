<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Reports\ReportBranchResolver;
use App\Services\Settings\BusinessProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BusinessProfileTest extends TestCase
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function save(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $this->branch->id])->put(route('dashboard.business-profile.update'), [
            'name' => 'Mama Put Kitchen',
            'tagline' => 'Home-cooked food in Osu',
            'public_emails' => 'hello@mamaput.test',
            'contact_recipients' => 'owner@mamaput.test, chef@mamaput.test',
            'instagram_url' => 'https://instagram.com/mamaput',
            'primary_colour' => '#0055aa',
            'accent_colour' => '#6b7280', // the fallback accent: unchanged
            'main_branch_id' => $this->branch->id,
            ...$overrides,
        ]);
    }

    public function test_a_new_install_shows_no_yes_my_shawarma_details(): void
    {
        $page = $this->get(route('contact'))->assertOk()->getContent();

        foreach (['yesmygrill', 'yesmyshawarma@', 'ymgrillnshawarma', 'Ga Odumase', '+233 (0) 243 635 265', 'logo-web.png', 'Order online for pickup from our Accra branches'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $page, "found \"{$leftover}\" on a fresh install");
        }

        // The branch's own phone is what shows.
        $this->assertStringContainsString('+233 (0) 241 112 222', $page);
    }

    public function test_only_the_owner_can_edit_the_profile(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('dashboard.business-profile.edit'))->assertForbidden();
        $this->save($manager)->assertForbidden();
        $this->actingAs($manager)->get(route('dashboard.settings.index'))->assertDontSee('Business profile');

        $owner = $this->userWithRole('owner');
        $this->actingAs($owner)->get(route('dashboard.business-profile.edit'))->assertOk();
    }

    public function test_the_owner_saves_the_profile_and_the_site_follows_it(): void
    {
        $owner = $this->userWithRole('owner');
        $this->save($owner)->assertSessionHas('status', 'Business profile saved.');

        $profile = app(BusinessProfile::class);
        $this->assertSame('Mama Put Kitchen', $profile->name());
        $this->assertSame(['owner@mamaput.test', 'chef@mamaput.test'], $profile->contactRecipients());
        $this->assertSame(['instagram' => 'https://instagram.com/mamaput'], $profile->socialLinks());

        $home = $this->get(route('contact'))->assertOk();
        $home->assertSee('Mama Put Kitchen')
            ->assertSee('Home-cooked food in Osu')
            ->assertSee('hello@mamaput.test')
            ->assertSee('https://instagram.com/mamaput', false)
            ->assertDontSee('tiktok.com', false);
    }

    public function test_a_new_main_colour_gets_its_shades_and_reaches_the_pages(): void
    {
        $this->save($this->userWithRole('owner'));

        $colours = app(BusinessProfile::class)->colours();
        $this->assertSame(['#0055aa', '#b3cce6', '#004488'], [$colours['brand_primary'], $colours['brand_primary_light'], $colours['brand_primary_dark']]);
        // The accent didn't change, so its shades are left exactly as they were.
        $this->assertSame('#4b5563', $colours['brand_accent_dark']);

        $this->get(route('home'))->assertSee('--brand-primary:0 85 170;', false);
    }

    public function test_logo_and_icon_upload_and_removal(): void
    {
        Storage::fake('public');
        $owner = $this->userWithRole('owner');

        $this->save($owner, [
            'logo' => UploadedFile::fake()->image('logo.png', 700, 200),
            'icon' => UploadedFile::fake()->image('icon.png', 512, 512),
        ])->assertSessionHasNoErrors();

        $profile = app(BusinessProfile::class);
        $logo = Setting::where('key', BusinessProfile::LOGO)->value('value');
        Storage::disk('public')->assertExists($logo);
        $this->get(route('home'))->assertSee($profile->logoUrl(), false)->assertSee($profile->iconUrl(), false);

        $this->save($owner, ['remove_logo' => '1']);
        Storage::disk('public')->assertMissing($logo);
        $this->assertNull(app(BusinessProfile::class)->logoUrl());

        $this->save($owner, ['icon' => UploadedFile::fake()->image('wide.png', 600, 200)])->assertSessionHasErrors('icon');
    }

    public function test_the_main_branch_heads_general_reports(): void
    {
        $other = Branch::create([
            'name' => 'Labone', 'slug' => 'labone', 'phone' => '+233241113333', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->assertTrue(app(ReportBranchResolver::class)->headerBranch(null)->is($this->branch));

        $this->save($this->userWithRole('owner'), ['main_branch_id' => $other->id]);
        $this->assertTrue(app(ReportBranchResolver::class)->headerBranch(null)->is($other));
    }

    public function test_the_app_manifest_follows_the_profile(): void
    {
        $this->save($this->userWithRole('owner'));

        $this->get(route('manifest'))->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJson(['name' => 'Mama Put Kitchen', 'description' => 'Home-cooked food in Osu', 'theme_color' => '#0055aa']);
    }

    public function test_the_backfill_only_fills_an_install_that_already_has_orders(): void
    {
        Storage::fake('public');
        $migration = require database_path('migrations/2026_11_15_100000_fill_business_profile.php');

        $migration->up();
        $this->assertNull(Setting::where('key', BusinessProfile::TAGLINE)->value('value'), 'a new install must stay neutral');

        Order::create([
            'reference' => 'X-1', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 100, 'total' => 100,
            'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);
        Setting::create(['key' => 'order_reference_prefix_pos', 'value' => 'KEEP-ME']);

        $migration->up();

        $this->assertSame('Shawarma, burgers and more. Order online for pickup from our Accra branches.', Setting::where('key', BusinessProfile::TAGLINE)->value('value'));
        $this->assertSame('YMGS-WEB', Setting::where('key', 'order_reference_prefix_web')->value('value'));
        $this->assertSame('KEEP-ME', Setting::where('key', 'order_reference_prefix_pos')->value('value'), 'an existing setting is never overwritten');
        Storage::disk('public')->assertExists(Setting::where('key', BusinessProfile::LOGO)->value('value'));
    }
}
