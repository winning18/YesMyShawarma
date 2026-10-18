<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchWorkingHour;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_allows_everything_and_points_to_the_sitemap_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $response = $this->get('/robots.txt')->assertOk();

        $response->assertSee('Disallow:', false);
        $response->assertDontSee('Disallow: /', false);
        $response->assertSee('Sitemap: '.route('sitemap'), false);
    }

    // The business wants AI answer engines citing/training on this site
    // (menu, hours, policies) — that has to be a named, deliberate rule,
    // not just an accident of the blanket "User-agent: *" rule below it.
    public function test_robots_txt_explicitly_allows_known_ai_crawlers_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $content = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'CCBot'] as $bot) {
            $this->assertMatchesRegularExpression(
                '/User-agent: '.preg_quote($bot, '/').'\nDisallow:\n/',
                $content,
                "Expected an explicit allow rule for {$bot}.",
            );
        }
    }

    public function test_robots_txt_still_blocks_everything_outside_production_including_ai_crawlers(): void
    {
        $content = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertSame("User-agent: *\nDisallow: /\n", $content);
    }

    // Staging is test/demo data — it must never be indexed alongside the
    // real site, and the old static public/robots.txt file (identical on
    // every environment, since nginx serves it before Laravel ever runs)
    // couldn't express that at all.
    public function test_robots_txt_disallows_everything_outside_production(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $response->assertSee('Disallow: /', false);
        $response->assertDontSee('Sitemap:', false);
    }

    public function test_sitemap_lists_static_pages_and_every_active_menu_item(): void
    {
        $branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265', 'address' => 'Ga Odumase, Accra',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '00:00',
        ]);
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 3500,
        ]);
        $branch->menuItems()->attach($item->id, ['is_available' => true]);
        $inactiveItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Discontinued Item', 'slug' => 'discontinued-item',
            'base_price' => 1000, 'is_active' => false,
        ]);

        $response = $this->get(route('sitemap'))->assertOk();

        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<loc>'.route('home').'</loc>', false);
        $response->assertSee('<loc>'.route('menu.index').'</loc>', false);
        $response->assertSee('<loc>'.route('menu.show', $item).'</loc>', false);
        $response->assertDontSee(route('menu.show', $inactiveItem), false);
    }

    public function test_home_page_includes_restaurant_structured_data_with_real_configured_hours(): void
    {
        $branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265', 'address' => 'Ga Odumase, Accra',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '00:00',
        ]);
        BranchWorkingHour::create(['branch_id' => $branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);

        $content = $this->get(route('home'))->assertOk()->getContent();

        $json = $this->extractLdJson($content);
        $this->assertNotNull($json, 'Expected at least one application/ld+json script tag.');
        $this->assertSame('Restaurant', $json['@type']);
        $this->assertSame('+233243635265', $json['telephone']);
        $this->assertSame(5.67, $json['geo']['latitude']);
        $this->assertSame('Monday', $json['openingHoursSpecification'][0]['dayOfWeek']);
        $this->assertSame('10:00', $json['openingHoursSpecification'][0]['opens']);
    }

    public function test_home_page_restaurant_schema_omits_hours_when_unconfigured(): void
    {
        Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265', 'address' => 'Ga Odumase, Accra',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '00:00',
        ]);

        $json = $this->extractLdJson($this->get(route('home'))->assertOk()->getContent());

        $this->assertArrayNotHasKey('openingHoursSpecification', $json);
    }

    public function test_product_page_includes_product_structured_data(): void
    {
        $branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265', 'address' => 'Ga Odumase, Accra',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '00:00',
        ]);
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 3500,
        ]);
        $branch->menuItems()->attach($item->id, ['is_available' => true]);
        $this->get(route('branches.pick', $branch));

        $json = $this->extractLdJson($this->get(route('menu.show', $item))->assertOk()->getContent());

        $this->assertSame('Product', $json['@type']);
        $this->assertSame('Chicken Shawarma', $json['name']);
        $this->assertStringContainsString('Chicken Shawarma', $json['description']);
        $this->assertSame(config('app.name'), $json['brand']['name']);
        $this->assertSame('35.00', $json['offers']['price']);
        $this->assertSame('GHS', $json['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $json['offers']['availability']);
    }

    public function test_menu_page_includes_menu_structured_data(): void
    {
        $branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265', 'address' => 'Ga Odumase, Accra',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '00:00',
        ]);
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 3500,
        ]);
        $branch->menuItems()->attach($item->id, ['is_available' => true]);
        $this->get(route('branches.pick', $branch));

        $json = $this->extractLdJson($this->get(route('menu.index'))->assertOk()->getContent());

        $this->assertSame('Menu', $json['@type']);
        $this->assertSame('Shawarma', $json['hasMenuSection'][0]['name']);
        $this->assertSame('Chicken Shawarma', $json['hasMenuSection'][0]['hasMenuItem'][0]['name']);
        $this->assertSame('35.00', $json['hasMenuSection'][0]['hasMenuItem'][0]['offers']['price']);
    }

    public function test_faq_page_includes_faqpage_structured_data_matching_the_visible_accordion(): void
    {
        $response = $this->get(route('faq'))->assertOk();
        $json = $this->extractLdJson($response->getContent());

        $this->assertSame('FAQPage', $json['@type']);
        $this->assertSame('Do I need an account to order?', $json['mainEntity'][0]['name']);
        $this->assertSame('Question', $json['mainEntity'][0]['@type']);
        $this->assertSame('Answer', $json['mainEntity'][0]['acceptedAnswer']['@type']);

        $response->assertSee($json['mainEntity'][0]['name']);
        $response->assertSee($json['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_home_page_head_has_manifest_and_apple_touch_icon_links(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('<link rel="apple-touch-icon" href="'.asset('images/apple-touch-icon.png').'">', false);
        $response->assertSee('<link rel="manifest" href="'.asset('site.webmanifest').'">', false);
    }

    // Regression: the branded 404 view needs session/auth (the customer
    // layout's header checks @auth('customer') for login/signup links),
    // but a completely unmatched URI bypasses the 'web' middleware group
    // (no session) by default — crashed with "Session store not set on
    // request" until Route::fallback() picked it up as a real, fully
    // middleware-wrapped route instead.
    public function test_an_unknown_url_shows_the_branded_404_page(): void
    {
        $response = $this->get('/this-page-does-not-exist-at-all');

        $response->assertNotFound();
        $response->assertSee('We couldn&#039;t find that page', false);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractLdJson(string $html): ?array
    {
        if (! preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches)) {
            return null;
        }

        return json_decode($matches[1], true);
    }

    private function makeBranch(string $name, string $slug, array $extra = []): Branch
    {
        return Branch::create([
            'name' => $name, 'slug' => $slug, 'phone' => '+233243635265', 'address' => $name.' Junction',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '00:00', ...$extra,
        ]);
    }

    public function test_each_branch_has_its_own_page_with_restaurant_data_and_weekly_hours(): void
    {
        $branch = $this->makeBranch('Pokuase Y-Junction', 'pokuase-y-junction', ['ghanapost_code' => 'GE-123-4567']);
        $this->makeBranch('Ga Odumase', 'ga-odumase');
        BranchWorkingHour::create(['branch_id' => $branch->id, 'day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '23:59']);

        $response = $this->get('/branches/pokuase-y-junction')->assertOk();

        $response->assertSee('<title>Shawarma &amp; Burgers in Pokuase Y-Junction, Accra · '.config('app.name').'</title>', false);
        $response->assertSee('Opening hours');
        $response->assertSee('6:00pm – 11:59pm');
        $response->assertSee(route('branches.show', 'ga-odumase'), false);

        $json = $this->extractLdJson($response->getContent());
        $this->assertSame('Restaurant', $json['@type']);
        $this->assertSame(route('branches.show', $branch), $json['url']);
        $this->assertSame(route('menu.index'), $json['hasMenu']);
        $this->assertSame('GE-123-4567', $json['address']['postalCode']);
    }

    public function test_a_branch_name_ending_in_branch_reads_naturally_in_the_title(): void
    {
        $this->makeBranch('GIMPA Branch', 'gimpa-branch');

        $this->get('/branches/gimpa-branch')
            ->assertOk()
            ->assertSee('<title>Shawarma &amp; Burgers in GIMPA, Accra', false);
    }

    public function test_an_inactive_branch_page_is_not_found(): void
    {
        $this->makeBranch('Closed Place', 'closed-place', ['is_active' => false]);

        $this->get('/branches/closed-place')->assertNotFound();
    }

    public function test_the_branch_page_route_never_swallows_the_staff_branch_picker(): void
    {
        // Guest hitting the staff picker is sent to log in — not a 404 from
        // the public branch page treating "select" as a slug.
        $this->get('/branches/select')->assertRedirect();
    }

    public function test_restaurant_phone_is_given_to_google_in_international_format(): void
    {
        $this->makeBranch('GIMPA Branch', 'gimpa-branch', ['phone' => '026-370-2929']);

        $json = $this->extractLdJson($this->get(route('home'))->getContent());

        $this->assertSame('+233263702929', $json['telephone']);
    }

    public function test_sitemap_lists_branch_pages_and_sold_out_items_but_not_items_off_the_default_menu(): void
    {
        $main = $this->makeBranch('Ga Odumase', 'ga-odumase');
        $other = $this->makeBranch('Pokuase Y-Junction', 'pokuase-y-junction');
        $category = Category::create(['name' => 'Sandwiches', 'slug' => 'sandwiches']);
        $soldOut = MenuItem::create(['category_id' => $category->id, 'name' => 'Egg Sandwich', 'slug' => 'egg-sandwich', 'base_price' => 2000]);
        $main->menuItems()->attach($soldOut->id, ['is_available' => false]);
        $elsewhere = MenuItem::create(['category_id' => $category->id, 'name' => 'Pokuase Special', 'slug' => 'pokuase-special', 'base_price' => 2000]);
        $other->menuItems()->attach($elsewhere->id, ['is_available' => true]);

        $response = $this->get(route('sitemap'))->assertOk();

        $response->assertSee('<loc>'.route('branches.show', $main).'</loc>', false);
        $response->assertSee('<loc>'.route('branches.show', $other).'</loc>', false);
        $response->assertSee('<loc>'.route('menu.show', $soldOut).'</loc>', false);
        $response->assertDontSee(route('menu.show', $elsewhere), false);

        // Every item URL the sitemap lists must actually load for a crawler.
        $this->get(route('menu.show', $soldOut))->assertOk();
    }

    public function test_public_pages_each_have_their_own_description(): void
    {
        $this->makeBranch('Ga Odumase', 'ga-odumase');

        $descriptions = collect(['home', 'menu.index', 'branches.index', 'about', 'contact', 'faq', 'reviews.index', 'policy.terms', 'policy.refunds', 'policy.privacy', 'policy.cookies'])
            ->mapWithKeys(function (string $route) {
                preg_match('/<meta name="description" content="([^"]*)"/', $this->get(route($route))->getContent(), $m);

                return [$route => $m[1] ?? null];
            });

        $this->assertNotContains(null, $descriptions->all());
        $this->assertSame($descriptions->count(), $descriptions->unique()->count(), 'Two pages share a meta description.');
    }
}
