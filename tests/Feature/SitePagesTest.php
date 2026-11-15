<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FaqItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SitePage;
use App\Models\User;
use App\Services\Settings\BusinessProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 2c: the website's words are content each business
 * writes under Settings → Website content.
 */
class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu Branch', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'Oxford Street',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        Setting::create(['key' => BusinessProfile::NAME, 'value' => 'Mama Put Kitchen']);
        // What AppServiceProvider does at the start of every real request.
        config(['app.name' => 'Mama Put Kitchen']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function asStaff(User $user): static
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $this->branch->id]);
    }

    public function test_a_new_install_has_no_about_faq_or_policy_pages_until_they_are_written(): void
    {
        foreach (['about', 'faq', 'policy.terms', 'policy.refunds', 'policy.privacy', 'policy.cookies'] as $route) {
            $this->get(route($route))->assertNotFound();
        }

        $home = $this->get(route('home'))->assertOk();
        $home->assertSee('<title>Mama Put Kitchen: Order Online</title>', false)
            ->assertDontSee(route('about'), false)
            ->assertDontSee(route('faq'), false)
            ->assertDontSee(route('policy.terms'), false)
            ->assertDontSee('Shawarma');

        $this->get(route('sitemap'))->assertDontSee(route('about'), false)->assertDontSee(route('policy.privacy'), false);

        $llms = $this->get(route('llms'))->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("# Mama Put Kitchen\n", $llms);
        $this->assertStringContainsString(route('menu.index'), $llms);
        $this->assertStringNotContainsString(route('about'), $llms);
        $this->assertStringNotContainsString('Policies', $llms);
    }

    public function test_owner_and_general_manager_edit_the_content_but_a_manager_cannot(): void
    {
        $this->asStaff($this->userWithRole('manager'))->get(route('dashboard.site-pages.index'))->assertForbidden();
        $this->asStaff($this->userWithRole('manager'))->put(route('dashboard.site-pages.update', 'terms'), ['body' => 'x'])->assertForbidden();

        $this->asStaff($this->userWithRole('owner'))->get(route('dashboard.site-pages.index'))->assertOk()->assertSee('Terms &amp; Conditions', false);

        $gm = $this->userWithRole('general_manager');
        $this->asStaff($gm)->get(route('dashboard.site-pages.edit', 'terms'))->assertOk();
        $this->asStaff($gm)->get(route('dashboard.site-pages.edit', 'not-a-page'))->assertNotFound();
        $this->asStaff($gm)->get(route('dashboard.faq-items.index'))->assertOk();
    }

    public function test_a_written_policy_appears_with_its_markdown_rendered_safely(): void
    {
        $this->asStaff($this->userWithRole('owner'))->put(route('dashboard.site-pages.update', 'terms'), [
            'title' => 'Our terms',
            'meta_title' => 'Terms · :name',
            'meta_description' => 'How ordering from :name works.',
            'body' => "*Last updated: 1 November 2026*\n\n## 1. Ordering {#ordering}\n\nYou order **online**. See [refunds](/refund-policy).\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
        ])->assertSessionHas('status');

        $page = $this->get(route('policy.terms'))->assertOk();
        $page->assertSee('<title>Terms · Mama Put Kitchen</title>', false)
            ->assertSee('<meta name="description" content="How ordering from Mama Put Kitchen works.">', false)
            ->assertSee('Our terms')
            ->assertSee('<h2 id="ordering">1. Ordering</h2>', false)
            ->assertSee('<strong>online</strong>', false)
            ->assertSee('<a href="/refund-policy">refunds</a>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false);

        // Now linked from the footer, the sitemap and llms.txt.
        $this->get(route('home'))->assertSee(route('policy.terms'), false);
        $this->get(route('sitemap'))->assertSee(route('policy.terms'), false);
        $this->get(route('llms'))->assertSee(route('policy.terms'), false);

        // Emptied again, it's taken off the site.
        $this->asStaff($this->userWithRole('owner'))->put(route('dashboard.site-pages.update', 'terms'), ['body' => '']);
        $this->get(route('policy.terms'))->assertNotFound();
    }

    public function test_branch_pages_fill_in_their_placeholders(): void
    {
        SitePage::create([
            'slug' => 'branch',
            'title' => 'Jollof in :area',
            'meta_title' => 'Food in :area, :city · :name',
            'meta_description' => ':name :branch, :address. Call :phone.',
            'body' => 'Find us at :address.',
        ]);
        Setting::create(['key' => BusinessProfile::CITY, 'value' => 'Accra']);
        $this->branch->update(['address' => 'Oxford *Street*']);

        $this->get(route('branches.show', $this->branch))->assertOk()
            ->assertSee('<title>Food in Osu, Accra · Mama Put Kitchen</title>', false)
            ->assertSee('Mama Put Kitchen Osu Branch, Oxford *Street*. Call +233241112222.', false)
            ->assertSee('Jollof in Osu')
            // A typed address is never read as Markdown.
            ->assertSee('Find us at Oxford *Street*.', false);
    }

    public function test_the_faq_is_edited_and_its_page_and_structured_data_follow(): void
    {
        $gm = $this->userWithRole('general_manager');

        $this->asStaff($gm)->post(route('dashboard.faq-items.store'), ['question' => 'Do you deliver?', 'answer' => 'Yes, across Osu.'])->assertSessionHas('status');
        $this->asStaff($gm)->post(route('dashboard.faq-items.store'), ['question' => 'Can I pay by MoMo?', 'answer' => 'Yes.', 'sort_order' => 0]);
        $this->asStaff($gm)->post(route('dashboard.faq-items.store'), ['question' => '', 'answer' => ''])->assertSessionHasErrors(['question', 'answer']);

        $page = $this->get(route('faq'))->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'Do you deliver?'), strpos($page, 'Can I pay by MoMo?'));
        $this->assertStringContainsString('"@type":"FAQPage"', $page);
        $this->assertStringContainsString('"name":"Do you deliver?"', $page);

        $item = FaqItem::where('question', 'Do you deliver?')->first();
        $this->asStaff($gm)->put(route('dashboard.faq-items.update', $item), ['question' => 'Do you deliver to Labone?', 'answer' => 'Yes.', 'sort_order' => 5]);
        $this->get(route('faq'))->assertSee('Do you deliver to Labone?');

        FaqItem::where('question', '!=', 'Do you deliver to Labone?')->delete();
        $this->asStaff($gm)->delete(route('dashboard.faq-items.destroy', $item->refresh()));
        $this->get(route('faq'))->assertNotFound();
    }

    public function test_the_about_page_shows_only_the_parts_written(): void
    {
        $this->asStaff($this->userWithRole('owner'))->put(route('dashboard.site-pages.update', 'about'), [
            'mission' => 'Good food, fast.',
            'founded_year' => '',
            'customers_label' => '',
        ])->assertSessionHasNoErrors();

        $this->get(route('about'))->assertOk()
            ->assertSee('Good food, fast.')
            ->assertDontSee('Vision')
            ->assertDontSee('Our success story')
            ->assertDontSee('Years of operation');

        $this->asStaff($this->userWithRole('owner'))->put(route('dashboard.site-pages.update', 'about'), [
            'body' => 'We started in **2020**.',
            'founded_year' => now()->year - 6,
        ]);

        $this->get(route('about'))->assertOk()
            ->assertDontSee('Good food, fast.')
            ->assertSee('<strong>2020</strong>', false)
            ->assertSee('Years of operation')
            ->assertDontSee('Happy customers');
    }

    public function test_city_region_and_food_from_the_profile_reach_googles_data(): void
    {
        Setting::create(['key' => BusinessProfile::CITY, 'value' => 'Kumasi']);
        Setting::create(['key' => BusinessProfile::REGION, 'value' => 'Ashanti']);
        Setting::create(['key' => BusinessProfile::CUISINES, 'value' => 'Ghanaian, Grills']);

        $page = $this->get(route('branches.show', $this->branch))->assertOk()->getContent();
        $this->assertStringContainsString('"addressLocality":"Kumasi","addressRegion":"Ashanti"', $page);
        $this->assertStringContainsString('"servesCuisine":["Ghanaian","Grills"]', $page);

        Setting::whereIn('key', [BusinessProfile::CITY, BusinessProfile::REGION, BusinessProfile::CUISINES])->delete();
        $page = $this->get(route('branches.show', $this->branch))->getContent();
        $this->assertStringNotContainsString('addressLocality', $page);
        $this->assertStringNotContainsString('servesCuisine', $page);
    }

    public function test_a_written_llms_summary_uses_this_sites_address(): void
    {
        SitePage::create(['slug' => 'llms', 'body' => "# :name\n\n- [Menu](:url/menu)"]);

        $this->get(route('llms'))->assertOk()->assertSeeText("# Mama Put Kitchen\n\n- [Menu](".url('/menu').')', false);
    }

    public function test_the_backfill_only_fills_an_install_that_already_has_orders(): void
    {
        $migration = require database_path('migrations/2026_11_15_120000_create_site_pages_and_faq_items.php');
        $migration->down();
        $migration->up();
        $this->assertSame(0, SitePage::count(), 'a new install must stay empty');

        $migration->down();
        Order::create([
            'reference' => 'X-1', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 100, 'total' => 100,
            'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);
        Setting::create(['key' => 'about_customers_label', 'value' => 'KEEP-ME']);
        $migration->up();

        $this->assertSame(15, SitePage::count());
        $this->assertSame(6, FaqItem::count());
        $this->assertStringContainsString('Yes! My Shawarma', SitePage::where('slug', 'terms')->value('body'));
        $this->assertSame('Accra', Setting::where('key', BusinessProfile::CITY)->value('value'));
        $this->assertSame('KEEP-ME', Setting::where('key', 'about_customers_label')->value('value'), 'an existing setting is never overwritten');
    }
}
