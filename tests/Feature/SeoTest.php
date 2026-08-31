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
        $this->assertSame('35.00', $json['offers']['price']);
        $this->assertSame('GHS', $json['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $json['offers']['availability']);
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
        $response->assertSee("We couldn&#039;t find that page", false);
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
}
