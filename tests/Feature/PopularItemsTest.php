<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Menu\PopularItemsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopularItemsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
    }

    private function item(string $name): MenuItem
    {
        $item = MenuItem::create([
            'category_id' => $this->category->id, 'name' => $name, 'slug' => str($name)->slug(), 'base_price' => 3000,
        ]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => true]);

        return $item;
    }

    private function sell(MenuItem $item, int $quantity, string $status = 'delivered', int $daysAgo = 1): void
    {
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(), 'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup',
            'subtotal' => 3000 * $quantity, 'total' => 3000 * $quantity,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'channel' => 'pos',
        ]);
        $order->status = $status;
        $order->placed_at = now()->subDays($daysAgo);
        $order->save();

        OrderItem::create([
            'order_id' => $order->id, 'menu_item_id' => $item->id, 'name_snapshot' => $item->name,
            'unit_price_snapshot' => 3000, 'quantity' => $quantity, 'line_total' => 3000 * $quantity,
        ]);
    }

    public function test_items_are_ranked_by_units_sold_in_the_last_30_days(): void
    {
        $chicken = $this->item('Chicken Shawarma');
        $beef = $this->item('Beef Shawarma');
        $mixed = $this->item('Mixed Shawarma');

        $this->sell($chicken, 6);
        $this->sell($beef, 12);
        $this->sell($mixed, 40, daysAgo: 45); // too old to count
        $this->sell($mixed, 9, status: 'cancelled'); // not a real sale

        $this->assertSame([$beef->id => 1, $chicken->id => 2], app(PopularItemsService::class)->ranks());
    }

    public function test_an_item_needs_a_minimum_sold_to_rank(): void
    {
        $this->sell($this->item('Chicken Shawarma'), PopularItemsService::MIN_SOLD - 1);

        $this->assertSame([], app(PopularItemsService::class)->ranks());
    }

    public function test_homepage_menu_and_item_page_show_popularity(): void
    {
        $beef = $this->item('Beef Shawarma');
        $chicken = $this->item('Chicken Shawarma');
        $plain = $this->item('Plain Shawarma');
        $this->sell($beef, 12);
        $this->sell($chicken, 6);
        $this->get(route('branches.pick', $this->branch));

        $this->get(route('home'))->assertOk()
            ->assertSee('Most ordered this month')
            ->assertSeeInOrder(['#1', 'Beef Shawarma', '#2', 'Chicken Shawarma']);

        $menu = $this->get(route('menu.index'))->assertOk();
        $this->assertSame([$beef->id, $chicken->id], $menu->viewData('popularIds'));
        $menu->assertSee('Popular');

        $this->get(route('menu.show', $beef))->assertOk()->assertSee('Most ordered this month');
        $this->get(route('menu.show', $chicken))->assertOk()->assertSee('#2 most ordered this month');
        $this->get(route('menu.show', $plain))->assertOk()->assertDontSee('most ordered this month');
    }

    public function test_homepage_has_no_strip_before_anything_qualifies(): void
    {
        $this->item('Chicken Shawarma');

        $this->get(route('home'))->assertOk()->assertDontSee('Most ordered this month');
    }
}
