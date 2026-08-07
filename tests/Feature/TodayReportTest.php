<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TodayReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $shawarmaCategory;

    private MenuItem $chickenShawarma;

    private MenuItem $beefShawarma;

    private MenuItem $signature;

    private Option $cheese;

    private Option $sausage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->shawarmaCategory = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);

        $this->chickenShawarma = MenuItem::create([
            'category_id' => $this->shawarmaCategory->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->beefShawarma = MenuItem::create([
            'category_id' => $this->shawarmaCategory->id, 'name' => 'Beef Shawarma', 'slug' => 'beef-shawarma', 'base_price' => 6500,
        ]);
        $this->signature = MenuItem::create([
            'category_id' => $this->shawarmaCategory->id, 'name' => 'Signature (Chicken, Cheese & Sausage)', 'slug' => 'signature', 'base_price' => 7000,
        ]);

        $extras = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 6]);
        $this->cheese = Option::create(['option_group_id' => $extras->id, 'name' => 'Cheese', 'price_delta' => 1000]);
        $this->sausage = Option::create(['option_group_id' => $extras->id, 'name' => 'Sausage', 'price_delta' => 1000]);

        $this->signature->components()->create(['component_type' => 'base', 'component_menu_item_id' => $this->chickenShawarma->id, 'quantity' => 1]);
        $this->signature->components()->create(['component_type' => 'modifier', 'component_option_id' => $this->cheese->id, 'quantity' => 1]);
        $this->signature->components()->create(['component_type' => 'modifier', 'component_option_id' => $this->sausage->id, 'quantity' => 1]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        return $staff;
    }

    private function makeOrder(string $channel = 'pos', string $paymentMethod = 'cash', string $status = 'delivered', ?Carbon $placedAt = null): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
            'channel' => $channel,
        ]);
        $order->status = $status;
        $order->placed_at = $placedAt ?? now('Africa/Accra');
        $order->save();

        return $order;
    }

    private function addItem(Order $order, MenuItem $menuItem, int $quantity = 1, array $selectedOptions = []): OrderItem
    {
        $unitPrice = $menuItem->base_price;
        $optionsTotal = array_sum(array_column($selectedOptions, 'price_delta'));
        $lineTotal = ($unitPrice + $optionsTotal) * $quantity;

        $orderItem = $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'name_snapshot' => $menuItem->name,
            'unit_price_snapshot' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
        ]);

        foreach ($selectedOptions as $option) {
            $orderItem->options()->create([
                'option_id' => $option->id,
                'name_snapshot' => $option->name,
                'price_delta_snapshot' => $option->price_delta,
            ]);
        }

        $order->increment('subtotal', $lineTotal);
        $order->increment('total', $lineTotal);

        return $orderItem;
    }

    public function test_staff_can_view_the_today_report(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->get(route('dashboard.reports.today.index'))
            ->assertOk()
            ->assertSee('Today');
    }

    public function test_yesterdays_orders_are_excluded(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makeOrder(placedAt: now('Africa/Accra')->subDay());
        $this->addItem($order, $this->chickenShawarma, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));

        $this->assertSame(0, $response->viewData('summary')['orders_count']);
    }

    public function test_non_revenue_orders_are_excluded(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makeOrder(status: 'cancelled');
        $this->addItem($order, $this->chickenShawarma, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));

        $this->assertSame(0, $response->viewData('summary')['orders_count']);
    }

    public function test_channel_toggle_separates_web_and_pos(): void
    {
        $staff = $this->makeStaff();
        $posOrder = $this->makeOrder(channel: 'pos');
        $this->addItem($posOrder, $this->chickenShawarma, 1);
        $webOrder = $this->makeOrder(channel: 'web');
        $this->addItem($webOrder, $this->beefShawarma, 1);

        $posResponse = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $this->assertSame(1, $posResponse->viewData('summary')['orders_count']);
        $posResponse->assertSee('Chicken Shawarma');
        $posResponse->assertDontSee('Beef Shawarma');

        $webResponse = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'web']));
        $this->assertSame(1, $webResponse->viewData('summary')['orders_count']);
        $webResponse->assertSee('Beef Shawarma');
        $webResponse->assertDontSee('Chicken Shawarma');
    }

    public function test_a_plain_item_bought_five_times_across_orders_aggregates(): void
    {
        $staff = $this->makeStaff();

        $order1 = $this->makeOrder();
        $this->addItem($order1, $this->chickenShawarma, 3);
        $order2 = $this->makeOrder();
        $this->addItem($order2, $this->chickenShawarma, 2);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $categories = $response->viewData('summary')['categories'];
        $shawarmaLine = $categories->firstWhere('category', 'Shawarma')['items']->firstWhere('name', 'Chicken Shawarma');

        $this->assertSame(5, $shawarmaLine['qty']);
        $this->assertSame(5000, $shawarmaLine['unit']);
        $this->assertSame(25000, $shawarmaLine['total']);
    }

    public function test_combo_item_decomposes_into_base_and_modifiers(): void
    {
        // Worked example 1 from the spec: Signature -> 1x Chicken Shawarma
        // (base) + 1x Cheese + 1x Sausage (modifiers), no real selections.
        $staff = $this->makeStaff();
        $order = $this->makeOrder();
        $this->addItem($order, $this->signature, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $summary = $response->viewData('summary');

        $shawarmaGroup = $summary['categories']->firstWhere('category', 'Shawarma');
        $baseLine = $shawarmaGroup['items']->firstWhere('name', 'Chicken Shawarma');
        $this->assertSame(1, $baseLine['qty']);
        $this->assertSame(5000, $baseLine['unit']);
        $this->assertSame(5000, $baseLine['total']);

        // The combo itself is never recorded as its own line.
        $this->assertNull($shawarmaGroup['items']->firstWhere('name', 'Signature (Chicken, Cheese & Sausage)'));

        $cheeseLine = $summary['modifiers']['items']->firstWhere('name', 'Cheese');
        $sausageLine = $summary['modifiers']['items']->firstWhere('name', 'Sausage');
        $this->assertSame(1, $cheeseLine['qty']);
        $this->assertSame(1000, $cheeseLine['total']);
        $this->assertSame(1, $sausageLine['qty']);
        $this->assertSame(1000, $sausageLine['total']);
    }

    public function test_combo_item_plus_a_real_modifier_selection_adds_up(): void
    {
        // Worked example 2 from the spec: Signature's implied 1x Cheese
        // plus a genuinely-selected Cheese option on the same order item
        // sums to 2x Cheese. (order_item_options has no quantity column —
        // an option can only be selected once per order item today — so
        // this covers what's actually representable: implied + one real
        // selection, not the exact "Sausage 2x" figure from the spec.)
        $staff = $this->makeStaff();
        $order = $this->makeOrder();
        $this->addItem($order, $this->signature, 1, [$this->cheese]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $summary = $response->viewData('summary');

        $cheeseLine = $summary['modifiers']['items']->firstWhere('name', 'Cheese');
        $sausageLine = $summary['modifiers']['items']->firstWhere('name', 'Sausage');

        $this->assertSame(2, $cheeseLine['qty']);
        $this->assertSame(2000, $cheeseLine['total']);
        // Sausage wasn't re-selected — stays at the implied 1.
        $this->assertSame(1, $sausageLine['qty']);
    }

    public function test_multiple_combos_sharing_a_base_item_aggregate_together(): void
    {
        $mamies = MenuItem::create([
            'category_id' => $this->shawarmaCategory->id, 'name' => 'Mamies (Chicken & Cheese)', 'slug' => 'mamies', 'base_price' => 6000,
        ]);
        $mamies->components()->create(['component_type' => 'base', 'component_menu_item_id' => $this->chickenShawarma->id, 'quantity' => 1]);
        $mamies->components()->create(['component_type' => 'modifier', 'component_option_id' => $this->cheese->id, 'quantity' => 1]);

        $staff = $this->makeStaff();
        $order = $this->makeOrder();
        $this->addItem($order, $this->signature, 1);
        $this->addItem($order, $mamies, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $summary = $response->viewData('summary');

        $baseLine = $summary['categories']->firstWhere('category', 'Shawarma')['items']->firstWhere('name', 'Chicken Shawarma');
        $this->assertSame(2, $baseLine['qty']);

        $cheeseLine = $summary['modifiers']['items']->firstWhere('name', 'Cheese');
        $this->assertSame(2, $cheeseLine['qty']);
    }

    public function test_daily_total_and_payment_method_breakdown(): void
    {
        $staff = $this->makeStaff();

        $cashOrder = $this->makeOrder(paymentMethod: 'cash');
        $this->addItem($cashOrder, $this->chickenShawarma, 1);
        $momoOrder = $this->makeOrder(paymentMethod: 'momo');
        $this->addItem($momoOrder, $this->beefShawarma, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $summary = $response->viewData('summary');

        $this->assertSame(5000 + 6500, $summary['total_sales']);
        $this->assertSame(5000, $summary['by_payment_method']['cash']);
        $this->assertSame(6500, $summary['by_payment_method']['momo']);
    }

    public function test_plain_item_uses_its_own_snapshot_price_not_live_price(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makeOrder();
        $this->addItem($order, $this->chickenShawarma, 1);

        // Price changes after the order was placed — the report must
        // still reflect what was actually charged that day.
        $this->chickenShawarma->update(['base_price' => 9999]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $line = $response->viewData('summary')['categories']->firstWhere('category', 'Shawarma')['items']->firstWhere('name', 'Chicken Shawarma');

        $this->assertSame(5000, $line['unit']);
    }
}
