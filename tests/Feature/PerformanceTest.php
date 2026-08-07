<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\VisitorSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $osu;

    private Branch $eastLegon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->osu = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->eastLegon = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeOwner(): User
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        return $owner;
    }

    private function makeOrder(Branch $branch, string $status, int $total, int $minutesAgo = 0, string $channel = 'web'): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'channel' => $channel,
        ]);
        $order->status = $status;
        $order->placed_at = now('Africa/Accra')->subMinutes($minutesAgo);
        $order->save();

        return $order;
    }

    public function test_owner_can_view_the_performance_page(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->get(route('dashboard.performance'))
            ->assertOk()
            ->assertSee('Performance')
            ->assertSee('Sales');
    }

    public function test_manager_cannot_view_the_performance_page(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);

        $this->actingAs($manager)->get(route('dashboard.performance'))->assertForbidden();
    }

    public function test_sales_tab_aggregates_revenue_and_orders_across_branches(): void
    {
        $owner = $this->makeOwner();

        $this->makeOrder($this->osu, 'delivered', 5000);
        $this->makeOrder($this->eastLegon, 'delivered', 3000);
        $this->makeOrder($this->eastLegon, 'cancelled', 9000);

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today']));

        $summary = $response->viewData('summary');

        $this->assertSame(8000, $summary['sales']['value']);
        $this->assertSame(3, $summary['orders']['value']);
    }

    public function test_sales_tab_computes_change_versus_the_previous_equivalent_period(): void
    {
        $owner = $this->makeOwner();

        $this->makeOrder($this->osu, 'delivered', 10000, minutesAgo: 0);
        $this->makeOrder($this->osu, 'delivered', 5000, minutesAgo: 60 * 24);

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today']));

        $summary = $response->viewData('summary');

        $this->assertSame(10000, $summary['sales']['value']);
        $this->assertSame(100.0, $summary['sales']['change_pct']);
    }

    public function test_item_sales_are_aggregated_and_sorted(): void
    {
        $owner = $this->makeOwner();
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $popular = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $rare = MenuItem::create(['category_id' => $category->id, 'name' => 'Beef Shawarma', 'slug' => 'beef-shawarma', 'base_price' => 6000]);

        $order = $this->makeOrder($this->osu, 'delivered', 16000);
        OrderItem::create([
            'order_id' => $order->id, 'menu_item_id' => $popular->id, 'name_snapshot' => $popular->name,
            'unit_price_snapshot' => 5000, 'quantity' => 2, 'line_total' => 10000,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'menu_item_id' => $rare->id, 'name_snapshot' => $rare->name,
            'unit_price_snapshot' => 6000, 'quantity' => 1, 'line_total' => 6000,
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today', 'sort' => 'amount_sold', 'dir' => 'desc']));

        $items = collect($response->viewData('itemSales')->items());

        $this->assertSame('Chicken Shawarma', $items->first()['name']);
        $this->assertSame(2, $items->first()['amount_sold']);
        $this->assertSame(10000, $items->first()['item_sales']);
        $this->assertEqualsWithDelta(62.5, $items->first()['sales_share_pct'], 0.1);
    }

    public function test_item_sales_excludes_non_revenue_orders(): void
    {
        $owner = $this->makeOwner();
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);

        $cancelled = $this->makeOrder($this->osu, 'cancelled', 5000);
        OrderItem::create([
            'order_id' => $cancelled->id, 'menu_item_id' => $item->id, 'name_snapshot' => $item->name,
            'unit_price_snapshot' => 5000, 'quantity' => 1, 'line_total' => 5000,
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today']));

        $this->assertCount(0, $response->viewData('itemSales')->items());
    }

    public function test_operations_tab_shows_per_branch_breakdown(): void
    {
        $owner = $this->makeOwner();

        $this->makeOrder($this->osu, 'delivered', 5000);
        $this->makeOrder($this->eastLegon, 'delivered', 3000);
        $this->makeOrder($this->eastLegon, 'delivered', 3000);

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'operations', 'range' => 'today']));

        $branches = $response->viewData('branches');
        $eastLegonRow = $branches->firstWhere(fn ($row) => $row['branch']->id === $this->eastLegon->id);

        $this->assertSame(2, $eastLegonRow['orders']);
        $this->assertSame(6000, $eastLegonRow['revenue']);
    }

    public function test_operations_tab_shows_escalated_orders_per_branch(): void
    {
        $owner = $this->makeOwner();

        $escalated = $this->makeOrder($this->osu, 'paid', 4000);
        $escalated->events()->create([
            'from_status' => null, 'to_status' => 'paid', 'actor_type' => 'system', 'actor_id' => null,
            'meta' => ['escalation_level' => 5],
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'operations', 'range' => 'today']));

        $branches = $response->viewData('branches');
        $osuRow = $branches->firstWhere(fn ($row) => $row['branch']->id === $this->osu->id);

        $this->assertSame(1, $osuRow['escalated']);
    }

    public function test_performance_page_shows_all_branches_even_when_owner_has_narrowed_via_the_switcher(): void
    {
        $owner = $this->makeOwner();
        $this->assignRoleAt($owner, 'owner', $this->eastLegon);

        $this->makeOrder($this->osu, 'delivered', 5000);
        $this->makeOrder($this->eastLegon, 'delivered', 3000);

        $response = $this->actingAs($owner)
            ->withSession(['current_branch_id' => $this->eastLegon->id])
            ->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today']));

        $this->assertSame(8000, $response->viewData('summary')['sales']['value']);
    }

    public function test_conversion_rate_reflects_visitor_sessions_that_ordered(): void
    {
        $owner = $this->makeOwner();

        VisitorSession::create(['token' => 'a', 'order_id' => $this->makeOrder($this->osu, 'delivered', 5000)->id]);
        VisitorSession::create(['token' => 'b']);
        VisitorSession::create(['token' => 'c']);
        VisitorSession::create(['token' => 'd']);

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today']));

        $this->assertSame(25.0, $response->viewData('summary')['conversion']['value']);
    }

    public function test_conversion_rate_is_null_with_no_visits(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today']));

        $this->assertNull($response->viewData('summary')['conversion']['value']);
    }
}
