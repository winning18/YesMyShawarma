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
use App\Models\Refund;
use App\Models\Shift;
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
            ->assertSee('Sales');
    }

    public function test_with_no_params_the_default_view_is_the_branchs_current_shift_not_calendar_today(): void
    {
        // The whole point of Phase B — a shift, not a calendar day, is the
        // atomic sales record, so landing on Sales with no params at all
        // must resolve to an actual shift rather than guessing "today".
        $staff = $this->makeStaff();
        $shift = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => now()->subHour(), 'ended_at' => null,
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('isCalendarMode'));
        $this->assertSame($shift->id, $response->viewData('shift')->id);
    }

    public function test_with_no_shift_ever_recorded_the_default_view_falls_back_to_today_with_a_notice(): void
    {
        $staff = $this->makeStaff();

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('isCalendarMode'));
        $this->assertNull($response->viewData('shift'));
        $response->assertSee(__('No shifts recorded yet — showing today\'s calendar totals instead.'));
    }

    public function test_the_previous_shift_link_navigates_to_the_prior_shift_at_the_same_branch(): void
    {
        $staff = $this->makeStaff();
        $earlier = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addHours(8),
        ]);
        $current = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => now()->subHour(), 'ended_at' => null,
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index'));

        $this->assertSame($current->id, $response->viewData('shift')->id);
        $this->assertSame($earlier->id, $response->viewData('previousShift')->id);
        $this->assertNull($response->viewData('nextShift'));

        $previousResponse = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['shift' => $earlier->id]));
        $this->assertSame($earlier->id, $previousResponse->viewData('shift')->id);
        $this->assertSame($current->id, $previousResponse->viewData('nextShift')->id);
    }

    public function test_owner_can_drill_into_a_specific_branchs_sales_report_via_the_branch_param(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $otherBranch = Branch::create([
            'name' => 'Labone', 'slug' => 'labone', 'phone' => '+233200000004', 'address' => 'D',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $otherStaff = User::factory()->create();
        $this->assignRoleAt($otherStaff, 'staff', $otherBranch);
        $otherShift = Shift::create([
            'user_id' => $otherStaff->id, 'branch_id' => $otherBranch->id,
            'started_at' => now()->subHour(), 'ended_at' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard.reports.today.index', ['branch' => $otherBranch->id]));

        $response->assertOk();
        $this->assertSame($otherShift->id, $response->viewData('shift')->id);
        $this->assertSame($otherBranch->id, $response->viewData('viewingBranch')->id);
        $response->assertSee('Labone');
    }

    public function test_a_manager_submitting_a_branch_param_is_ignored_since_they_only_have_their_own(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $otherBranch = Branch::create([
            'name' => 'Labone', 'slug' => 'labone', 'phone' => '+233200000005', 'address' => 'E',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.today.index', ['branch' => $otherBranch->id]));

        $response->assertOk();
        $this->assertNull($response->viewData('viewingBranch'));
    }

    public function test_a_general_manager_cannot_drill_into_a_branch_outside_their_own_oversight(): void
    {
        $generalManager = User::factory()->create();
        $this->assignRoleAt($generalManager, 'general_manager', $this->branch);

        $otherBranch = Branch::create([
            'name' => 'Labone', 'slug' => 'labone', 'phone' => '+233200000006', 'address' => 'F',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $response = $this->actingAs($generalManager)->get(route('dashboard.reports.today.index', ['branch' => $otherBranch->id]));

        $response->assertOk();
        $this->assertNull($response->viewData('viewingBranch'));
    }

    public function test_a_shift_id_belonging_to_another_branch_is_silently_ignored(): void
    {
        $staff = $this->makeStaff();
        $otherBranch = Branch::create([
            'name' => 'Labone', 'slug' => 'labone', 'phone' => '+233200000003', 'address' => 'C',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $ownShift = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => now()->subHour(), 'ended_at' => null,
        ]);
        $otherShift = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $otherBranch->id,
            'started_at' => now()->subHour(), 'ended_at' => null,
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['shift' => $otherShift->id]));

        $this->assertSame($ownShift->id, $response->viewData('shift')->id);
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

    public function test_bolt_food_orders_are_excluded_from_todays_sales(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makeOrder(paymentMethod: 'bolt_food');
        $this->addItem($order, $this->chickenShawarma, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $summary = $response->viewData('summary');

        $this->assertSame(0, $summary['orders_count']);
        $this->assertSame(0, $summary['total_sales']);
        $this->assertNull($summary['categories']->firstWhere('category', 'Shawarma'));
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

    public function test_a_past_date_can_be_viewed_via_the_date_param(): void
    {
        $staff = $this->makeStaff();
        $yesterday = now('Africa/Accra')->subDay();
        $order = $this->makeOrder(placedAt: $yesterday);
        $this->addItem($order, $this->chickenShawarma, 1);

        // Viewing today (the default) must not show yesterday's order —
        // nothing "disappeared", it's just not in today's window.
        $todayResponse = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $this->assertSame(0, $todayResponse->viewData('summary')['orders_count']);

        // The exact same order, searched for on the day it actually
        // happened, is fully there — categories, modifiers and all.
        $pastResponse = $this->actingAs($staff)->get(route('dashboard.reports.today.index', [
            'channel' => 'pos', 'date' => $yesterday->toDateString(),
        ]));
        $summary = $pastResponse->viewData('summary');
        $this->assertSame(1, $summary['orders_count']);
        $this->assertNotNull($summary['categories']->firstWhere('category', 'Shawarma'));
        $this->assertTrue($pastResponse->viewData('isCalendarMode'));
    }

    public function test_bolt_food_channel_shows_only_bolt_food_orders(): void
    {
        $staff = $this->makeStaff();
        $realPosOrder = $this->makeOrder(paymentMethod: 'cash');
        $this->addItem($realPosOrder, $this->chickenShawarma, 1);
        $boltFoodOrder = $this->makeOrder(paymentMethod: 'bolt_food');
        $this->addItem($boltFoodOrder, $this->beefShawarma, 1);

        // The plain "POS" view still excludes Bolt Food entirely (it's not
        // this till's own money) — unchanged from before this existed.
        $posResponse = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));
        $this->assertSame(1, $posResponse->viewData('summary')['orders_count']);
        $posResponse->assertSee('Chicken Shawarma');
        $posResponse->assertDontSee('Beef Shawarma');

        // Selecting "Bolt Food" explicitly is the one place it's visible,
        // on its own, separate from real in-house POS sales.
        $boltResponse = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'bolt_food']));
        $boltSummary = $boltResponse->viewData('summary');
        $this->assertSame(1, $boltSummary['orders_count']);
        $boltResponse->assertSee('Beef Shawarma');
        $boltResponse->assertDontSee('Chicken Shawarma');
    }

    public function test_total_sales_nets_out_a_completed_refund_same_as_the_financial_report(): void
    {
        // Sales/Today used to sum gross order totals with no refund
        // netting at all, while Detailed reports' financial summary
        // (OrderReportService) always netted completed refunds out — the
        // same real-world sales could show two different "total sales"
        // figures depending which screen you were on. Both now share one
        // formula (see DailySalesReportService's class docblock).
        $staff = $this->makeStaff();
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $order = $this->makeOrder(paymentMethod: 'cash');
        $this->addItem($order, $this->chickenShawarma, 1);

        Refund::create([
            'order_id' => $order->id, 'branch_id' => $this->branch->id,
            'amount' => 2000, 'reason' => 'Customer complaint', 'status' => 'completed',
            'requested_by' => $manager->id, 'completed_by' => $manager->id, 'completed_at' => now(),
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['channel' => 'pos']));

        $this->assertSame(5000 - 2000, $response->viewData('summary')['total_sales']);
    }

    public function test_a_shift_report_scopes_to_exactly_that_shifts_window_across_midnight(): void
    {
        $staff = $this->makeStaff();
        $shift = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => Carbon::parse('2026-10-03 22:00:00', 'Africa/Accra'),
            'ended_at' => Carbon::parse('2026-10-04 02:00:00', 'Africa/Accra'),
        ]);

        // One order just before midnight, one just after — a shift that
        // ran 10pm-2am should see both in one report, not have them split
        // across two different calendar days.
        $lateOrder = $this->makeOrder(placedAt: Carbon::parse('2026-10-03 23:30:00', 'Africa/Accra'));
        $this->addItem($lateOrder, $this->chickenShawarma, 1);
        $earlyOrder = $this->makeOrder(placedAt: Carbon::parse('2026-10-04 00:45:00', 'Africa/Accra'));
        $this->addItem($earlyOrder, $this->beefShawarma, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', [
            'channel' => 'pos',
            'shift' => $shift->id,
        ]));

        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['orders_count']);
        $response->assertSee('Chicken Shawarma');
        $response->assertSee('Beef Shawarma');
        $this->assertFalse($response->viewData('isCalendarMode'));
        $this->assertSame($shift->id, $response->viewData('shift')->id);
    }

    public function test_a_single_shifts_own_report_does_not_show_the_shifts_table_itself(): void
    {
        // Reached via a shift row's "View full report" link — showing that
        // same table again underneath the report it was picked from was
        // redundant, not useful context (this used to render it anyway).
        $staff = $this->makeStaff();
        $shift = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => Carbon::parse('2026-10-03 23:00:00', 'Africa/Accra'),
            'ended_at' => Carbon::parse('2026-10-04 01:00:00', 'Africa/Accra'),
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['shift' => $shift->id]));

        $this->assertTrue($response->viewData('shifts')->isEmpty());
        $response->assertDontSee(__('Shifts that day'));
    }

    public function test_shifts_table_shows_a_date_and_flags_a_shift_that_ended_the_next_day(): void
    {
        $staff = $this->makeStaff();
        Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => Carbon::parse('2026-10-03 23:00:00', 'Africa/Accra'),
            'ended_at' => Carbon::parse('2026-10-04 01:00:00', 'Africa/Accra'),
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', ['date' => '2026-10-03']));

        $response->assertOk();
        $response->assertSee('03 Oct 2026');
        $response->assertSee(__('+1'));
    }

    public function test_shifts_table_does_not_crash_when_the_opener_account_has_since_been_deleted(): void
    {
        // Production bug: a staff account opened a shift, was later removed
        // (soft-deleted), and the Shifts table's "Opened by" column crashed
        // the whole page trying to read ->user->name off the now-null
        // relation — a viewer couldn't even see that branch's sales at all.
        $staff = $this->makeStaff();
        $viewer = $this->makeStaff();
        Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => Carbon::parse('2026-10-03 10:00:00', 'Africa/Accra'),
            'ended_at' => Carbon::parse('2026-10-03 18:00:00', 'Africa/Accra'),
        ]);
        $staff->delete();

        $response = $this->actingAs($viewer)->get(route('dashboard.reports.today.index', ['date' => '2026-10-03']));

        $response->assertOk();
        $response->assertSee(__('Unknown'));
    }

    /**
     * Regression: the custom-range view (reached via a shift's "View full
     * report" link) rendered its channel toggle links using the ordinary
     * date-based route, which dropped back to a whole-day view the moment
     * you switched to Bolt Food/Web — exactly the midnight-crossing
     * problem this feature exists to avoid, just one click deeper.
     */
    public function test_switching_channel_within_a_shift_report_stays_locked_to_that_shift(): void
    {
        $staff = $this->makeStaff();
        $shift = Shift::create([
            'user_id' => $staff->id, 'branch_id' => $this->branch->id,
            'started_at' => Carbon::parse('2026-10-03 22:00:00', 'Africa/Accra'),
            'ended_at' => Carbon::parse('2026-10-04 02:00:00', 'Africa/Accra'),
        ]);
        $boltOrder = $this->makeOrder(paymentMethod: 'bolt_food', placedAt: Carbon::parse('2026-10-04 00:30:00', 'Africa/Accra'));
        $this->addItem($boltOrder, $this->beefShawarma, 1);

        $response = $this->actingAs($staff)->get(route('dashboard.reports.today.index', [
            'channel' => 'pos', 'shift' => $shift->id,
        ]));

        $expectedBoltFoodLink = str_replace('&', '&amp;', route('dashboard.reports.today.index', [
            'shift' => $shift->id, 'channel' => 'bolt_food',
        ]));
        $response->assertSee($expectedBoltFoodLink, false);
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
