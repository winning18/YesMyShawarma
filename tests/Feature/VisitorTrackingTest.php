<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PageView;
use App\Models\VisitorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_the_customer_site_sets_a_visitor_cookie_and_records_a_session(): void
    {
        $response = $this->withHeader('User-Agent', 'TestBrowser/1.0')
            ->withHeader('Referer', 'https://google.com')
            ->get(route('home'));

        $response->assertOk();
        $this->assertDatabaseCount('visitor_sessions', 1);
        $this->assertNotNull($response->getCookie('visitor_token'));

        $session = VisitorSession::first();
        $this->assertSame('TestBrowser/1.0', $session->user_agent);
        $this->assertSame('https://google.com', $session->referrer);
        $this->assertNotNull($session->ip_address);
    }

    public function test_a_get_request_records_a_page_view(): void
    {
        $this->get(route('home'));

        $this->assertDatabaseCount('page_views', 1);
        $this->assertSame('/', PageView::first()->path);
        $this->assertNull(PageView::first()->duration_seconds);
    }

    public function test_a_post_request_does_not_record_a_page_view(): void
    {
        $this->post(route('cookie-consent.update'), ['choice' => 'accept']);

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_the_duration_beacon_updates_the_matching_page_view(): void
    {
        $home = $this->get(route('home'));
        $token = $home->getCookie('visitor_token')->getValue();
        $pageView = PageView::first();

        $this->withCookie('visitor_token', $token)
            ->post(route('page-views.duration', $pageView), ['duration_seconds' => 42])
            ->assertNoContent();

        $this->assertSame(42, $pageView->fresh()->duration_seconds);
    }

    public function test_the_duration_beacon_ignores_a_mismatched_visitor_token(): void
    {
        $this->get(route('home'));
        $pageView = PageView::first();

        $this->withCookie('visitor_token', 'someone-elses-token')
            ->post(route('page-views.duration', $pageView), ['duration_seconds' => 42])
            ->assertNoContent();

        $this->assertNull($pageView->fresh()->duration_seconds);
    }

    public function test_repeat_visits_with_the_same_cookie_do_not_create_duplicate_sessions(): void
    {
        $first = $this->get(route('home'));
        $token = $first->getCookie('visitor_token')->getValue();

        $this->withCookie('visitor_token', $token)->get(route('menu.index'));

        $this->assertDatabaseCount('visitor_sessions', 1);
    }

    public function test_repeat_visits_bump_last_seen(): void
    {
        $first = $this->get(route('home'));
        $token = $first->getCookie('visitor_token')->getValue();
        $firstSeenAt = VisitorSession::first()->updated_at;

        $this->travel(1)->hour();
        $this->withCookie('visitor_token', $token)->get(route('menu.index'));

        $this->assertTrue(VisitorSession::first()->updated_at->gt($firstSeenAt));
    }

    public function test_staff_login_page_is_not_tracked(): void
    {
        $this->get(route('login'));

        $this->assertDatabaseCount('visitor_sessions', 0);
    }

    public function test_checkout_marks_the_visitor_session_as_converted(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $branch->menuItems()->attach($item->id, ['is_available' => true]);

        $home = $this->get(route('home'));
        $token = $home->getCookie('visitor_token')->getValue();
        $this->withCookie('visitor_token', $token);

        $this->post(route('cart.add'), [
            'branch_id' => $branch->id, 'menu_item_id' => $item->id, 'quantity' => 1, 'option_ids' => [],
        ]);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Ama', 'phone' => '0241111111', 'payment_method' => 'cash',
        ]);

        $order = Order::first();
        $response->assertRedirect(route('checkout.confirmation', $order));

        $session = VisitorSession::where('token', $token)->first();
        $this->assertSame($order->id, $session->order_id);
        $this->assertSame($branch->id, $session->branch_id);
    }

    public function test_a_request_with_no_prior_cookie_does_not_convert_a_session(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $branch->menuItems()->attach($item->id, ['is_available' => true]);

        // Each call below is its own request with no cookie carried over
        // from the last (the test client doesn't round-trip Set-Cookie the
        // way a browser does) — same shape as a checkout.store request
        // that genuinely arrives with no visitor_token yet. Either way,
        // markConverted() must degrade to a no-op rather than error, and
        // certainly never attribute an order to the wrong/no session.
        $this->post(route('cart.add'), [
            'branch_id' => $branch->id, 'menu_item_id' => $item->id, 'quantity' => 1, 'option_ids' => [],
        ]);

        $this->post(route('checkout.store'), [
            'name' => 'Ama', 'phone' => '0241111111', 'payment_method' => 'cash',
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, VisitorSession::whereNotNull('order_id')->count());
    }
}
