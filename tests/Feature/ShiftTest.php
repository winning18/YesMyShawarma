<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function revenueOrder(int $total): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_staff_can_start_and_end_a_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->getJson(route('shift.show'))->assertJsonPath('active', false);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->getJson(route('shift.show'))->assertJsonPath('active', true);

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'branch_id' => $this->branch->id, 'ended_at' => null,
        ]);

        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '250.00', 'no_expenses' => true,
        ])->assertOk();

        $this->actingAs($staff)->getJson(route('shift.show'))->assertJsonPath('active', false);
    }

    public function test_starting_cash_is_optional_and_stored_in_pesewas(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'), ['starting_cash' => '100.50'])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'starting_cash' => 10050,
        ]);
    }

    public function test_shift_can_start_without_a_starting_cash_amount(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'starting_cash' => null,
        ]);
    }

    public function test_staff_must_enter_total_sales_to_end_a_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'))->assertUnprocessable();

        $this->assertDatabaseHas('shifts', ['user_id' => $staff->id, 'ended_at' => null]);
    }

    public function test_total_sales_is_stored_in_pesewas_for_staff(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();
        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '875.25', 'no_expenses' => true,
        ])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'total_sales' => 87525,
        ]);
    }

    public function test_manager_must_enter_total_sales_and_expenses_to_end_a_shift(): void
    {
        // total_sales and the expenses-or-none requirement apply to
        // whoever ends a shift now, not staff only.
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($manager)->postJson(route('shift.end'))->assertUnprocessable();

        $this->actingAs($manager)->postJson(route('shift.end'), [
            'total_sales' => '0.00',
        ])->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Add at least one expense, or confirm there were none today.']);

        $this->actingAs($manager)->postJson(route('shift.end'), [
            'total_sales' => '0.00', 'no_expenses' => true,
        ])->assertOk();

        $this->actingAs($manager)->getJson(route('shift.show'))->assertJsonPath('active', false);
    }

    public function test_staff_cannot_end_shift_reporting_less_than_the_shifts_system_sales(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(10000); // GHS 100.00 recorded by the system

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '50.00', 'no_expenses' => true])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => "Total sales cannot be less than this shift's recorded sales of GHS 100.00."]);

        $this->assertDatabaseHas('shifts', ['user_id' => $staff->id, 'ended_at' => null]);
    }

    public function test_bolt_food_orders_are_excluded_from_the_system_sales_check(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(10000); // GHS 100.00 — counts
        $this->revenueOrder(5000)->update(['payment_method' => 'bolt_food']); // GHS 50.00 — must not count

        // Reporting only the 100.00 that's actually in the till succeeds —
        // if the Bolt Food order counted too, this would be rejected as
        // under-reporting against a 150.00 system figure.
        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '100.00', 'no_expenses' => true,
        ])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'total_sales' => 10000, 'system_sales' => 10000,
        ]);
    }

    public function test_staff_can_end_shift_reporting_exactly_the_shifts_system_sales(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(10000);

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '100.00', 'no_expenses' => true])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'total_sales' => 10000, 'system_sales' => 10000,
        ]);
    }

    public function test_staff_reporting_more_than_system_sales_is_recorded_and_shown_in_the_report(): void
    {
        $staff = User::factory()->create(['name' => 'Ama Staff']);
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(10000); // GHS 100.00 recorded

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '130.00', 'no_expenses' => true])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'total_sales' => 13000, 'system_sales' => 10000,
        ]);

        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.reports.today.index'))
            ->assertOk()
            ->assertSee('Ama Staff')
            ->assertSee('GH₵30.00'); // the extra: 130.00 - 100.00
    }

    public function test_system_sales_does_not_include_orders_from_a_previous_shift(): void
    {
        // Regression: system_sales used to be a whole-calendar-day total
        // (OrderReportService::financialSummary() over the full Accra day),
        // so it never reset between shifts on the same day — a new shift
        // would immediately show the previous shift's sales as its own,
        // and reject an honest "0.00" as under-reporting.
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        // Explicit time travel, not just sequential calls — MySQL datetime
        // columns are second-granular, so two shifts started and ended
        // within the same real-world second (as a fast test easily can)
        // would otherwise share an identical started_at/placed_at and this
        // test would pass even with the bug still present.
        $this->travelTo(now()->setTime(10, 0));
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();
        $this->revenueOrder(10000); // GHS 100.00, rung up during shift 1
        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '100.00', 'no_expenses' => true])->assertOk();

        $this->travelTo(now()->addHour());
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->getJson(route('shift.show'))
            ->assertOk()
            ->assertJsonPath('system_sales', 0);

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '0.00', 'no_expenses' => true])
            ->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'total_sales' => 0, 'system_sales' => 0,
        ]);
    }

    public function test_shift_show_includes_system_sales_for_staff(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(5000);

        $this->actingAs($staff)->getJson(route('shift.show'))
            ->assertOk()
            ->assertJsonPath('system_sales', 5000);
    }

    public function test_shift_show_includes_system_sales_for_manager_too(): void
    {
        // Required and validated against for everyone now, not staff only.
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);
        $this->actingAs($manager)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(5000);

        $this->actingAs($manager)->getJson(route('shift.show'))
            ->assertOk()
            ->assertJsonPath('system_sales', 5000);
    }

    public function test_starting_a_shift_twice_joins_the_same_one_instead_of_erroring(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();
        $firstShiftId = Shift::where('branch_id', $this->branch->id)->whereNull('ended_at')->sole()->id;

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->assertSame(1, Shift::where('branch_id', $this->branch->id)->whereNull('ended_at')->count());
        $this->assertSame($firstShiftId, Shift::where('branch_id', $this->branch->id)->whereNull('ended_at')->sole()->id);
    }

    public function test_a_second_staff_member_joins_the_branchs_open_shift_without_starting_their_own(): void
    {
        $staff1 = User::factory()->create();
        $staff2 = User::factory()->create();
        $this->assignRoleAt($staff1, 'staff', $this->branch);
        $this->assignRoleAt($staff2, 'staff', $this->branch);

        $this->actingAs($staff1)->postJson(route('shift.start'), ['starting_cash' => '20.00'])->assertOk();

        // Staff 2 reaches the dashboard directly — no start-shift prompt at
        // all, since the branch's shift is already open.
        $this->actingAs($staff2)->get(route('dashboard'))->assertOk();

        // An order recorded while the shared shift is open counts toward
        // it regardless of which of the two staff members was the one
        // actually working when it came in.
        $this->revenueOrder(5000);

        $this->assertSame(1, Shift::where('branch_id', $this->branch->id)->whereNull('ended_at')->count());

        // Staff 2 (who never "started" anything) can still end the shared
        // shift — ending it ends it for the whole branch, not just whoever
        // opened it.
        $this->actingAs($staff2)->postJson(route('shift.end'), [
            'total_sales' => '50.00', 'no_expenses' => true,
        ])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'branch_id' => $this->branch->id,
            'user_id' => $staff1->id,
            'ended_by_user_id' => $staff2->id,
            'total_sales' => 5000,
            'system_sales' => 5000,
            'starting_cash' => 2000,
        ]);
    }

    public function test_ending_with_no_active_shift_is_rejected(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.end'))->assertUnprocessable();
    }

    public function test_order_actions_attribute_to_the_actors_active_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $customer = Customer::create(['phone' => '+233241111111']);
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->status = 'paid';
        $order->save();

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        $shift = Shift::where('user_id', $staff->id)->first();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'to_status' => 'accepted', 'shift_id' => $shift->id,
        ]);
    }

    public function test_staff_without_an_active_shift_gets_the_forced_start_popup(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('shiftWidget(true, true)', false);
    }

    public function test_staff_with_an_active_shift_does_not_get_the_forced_popup(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('shiftWidget(true, false)', false);
    }

    public function test_ending_a_shift_redirects_staff_to_reports_and_invoices(): void
    {
        // The redirect itself is client-side (JS, on a successful
        // shift.end) — this asserts the widget script is wired to the
        // right destination rather than the old page-reload behaviour.
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('dashboard.reports.index'), false);
    }

    public function test_manager_never_gets_the_forced_popup(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        // Manager's route('dashboard') redirects to the business overview
        // — dashboard.orders.live is where their (unforced) shift widget
        // actually lives now.
        $this->actingAs($manager)->get(route('dashboard'))
            ->assertRedirect(route('dashboard.performance'));

        $this->actingAs($manager)->get(route('dashboard.orders.live'))
            ->assertOk()
            ->assertSee('shiftWidget(false, false)', false);
    }

    public function test_dashboard_nav_link_is_greyed_out_for_staff_without_an_active_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        // /profile has nothing to do with shifts — the point is the nav
        // (rendered on every staff page via layouts.navigation) reflects
        // shift state everywhere, not only on dashboard-area pages.
        $response = $this->actingAs($staff)->get(route('profile.edit'))->assertOk();

        $response->assertSee(__('Start a shift to access the dashboard.'));
    }

    public function test_dashboard_nav_link_is_a_real_link_for_staff_with_an_active_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $response = $this->actingAs($staff)->get(route('profile.edit'))->assertOk();

        $response->assertDontSee(__('Start a shift to access the dashboard.'));
    }

    public function test_manager_never_sees_the_dashboard_link_greyed_out(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $response = $this->actingAs($manager)->get(route('profile.edit'))->assertOk();

        $response->assertDontSee(__('Start a shift to access the dashboard.'));
    }

    public function test_no_shift_widget_renders_outside_the_dashboard_area(): void
    {
        // Only one shift toggle ever exists on screen — the one in
        // dashboard/_channel-header.blade.php (Orders/POS/History). A
        // second, independent instance in the sidebar previously drifted
        // out of sync with it (two separate Alpine components, two
        // separate "Start shift"/"End shift" buttons that could disagree).
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('x-data="shiftWidget', false);
    }

    public function test_ending_a_shift_requires_at_least_one_expense_or_a_no_expenses_confirmation(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '100.00'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Add at least one expense, or confirm there were none today.']);

        $this->assertDatabaseHas('shifts', ['user_id' => $staff->id, 'ended_at' => null]);
    }

    public function test_no_expenses_checkbox_satisfies_the_requirement_with_zero_rows(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '100.00', 'no_expenses' => true,
        ])->assertOk();

        $this->assertDatabaseHas('shifts', ['user_id' => $staff->id, 'no_expenses' => true]);
        $this->assertDatabaseCount('shift_expenses', 0);
    }

    public function test_multiple_expenses_are_persisted_with_description_and_amount_in_pesewas(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '100.00',
            'expenses' => [
                ['description' => 'Gas refill', 'amount' => '25.00'],
                ['description' => 'Ice', 'amount' => '5.50'],
            ],
        ])->assertOk();

        $shift = Shift::where('user_id', $staff->id)->first();

        $this->assertDatabaseHas('shift_expenses', [
            'shift_id' => $shift->id, 'description' => 'Gas refill', 'amount' => 2500,
        ]);
        $this->assertDatabaseHas('shift_expenses', [
            'shift_id' => $shift->id, 'description' => 'Ice', 'amount' => 550,
        ]);
        $this->assertFalse($shift->no_expenses);
    }

    public function test_an_expense_row_missing_a_description_fails_validation(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '100.00',
            'expenses' => [['description' => '', 'amount' => '25.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('expenses.0.description');

        $this->assertDatabaseCount('shift_expenses', 0);
    }

    public function test_an_expense_row_missing_an_amount_fails_validation(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '100.00',
            'expenses' => [['description' => 'Gas refill', 'amount' => '']],
        ])->assertUnprocessable()->assertJsonValidationErrors('expenses.0.amount');

        $this->assertDatabaseCount('shift_expenses', 0);
    }

    public function test_expenses_are_deducted_from_total_sales_in_the_today_report(): void
    {
        $staff = User::factory()->create(['name' => 'Ama Staff']);
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '100.00',
            'expenses' => [['description' => 'Gas refill', 'amount' => '30.00']],
        ])->assertOk();

        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.reports.today.index'))
            ->assertOk()
            ->assertSee('Ama Staff')
            ->assertSee('GH₵30.00') // Expenses
            ->assertSee('GH₵70.00'); // Net: 100.00 - 30.00
    }

    public function test_a_confirmed_no_expenses_shift_shows_a_zero_in_the_report(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();
        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '50.00', 'no_expenses' => true,
        ])->assertOk();

        $shift = Shift::where('user_id', $staff->id)->first();

        $this->assertTrue($shift->no_expenses);
        $this->assertSame(0, $shift->expenses->sum('amount'));

        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.reports.today.index'))
            ->assertOk()
            ->assertSee('GH₵0.00'); // Expenses: confirmed none, not "—"
    }

    public function test_a_shift_from_before_this_feature_shows_a_dash_not_a_zero_in_the_report(): void
    {
        // Simulates a shift ended before this feature existed — no
        // shift_expenses rows, and no_expenses was never set either —
        // kept visually distinct from a confirmed-zero shift (schema.md).
        $legacy = User::factory()->create(['name' => 'Legacy Shift']);
        $this->assignRoleAt($legacy, 'staff', $this->branch);
        Shift::create([
            'user_id' => $legacy->id, 'branch_id' => $this->branch->id,
            'started_at' => now(), 'ended_at' => now(), 'total_sales' => 5000,
        ]);

        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $response = $this->actingAs($owner)->get(route('dashboard.reports.today.index'))->assertOk();
        $response->assertSeeInOrder(['Legacy Shift', '—']);
    }

    public function test_closing_note_can_be_left_when_ending_a_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.end'), [
            'total_sales' => '100.00', 'no_expenses' => true, 'closing_note' => 'Fridge was unplugged overnight.',
        ])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'closing_note' => 'Fridge was unplugged overnight.',
        ]);
    }

    public function test_end_shift_modal_includes_the_expenses_and_notes_fields(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $response = $this->actingAs($staff)->get(route('dashboard'))->assertOk();

        $response->assertSee(__('No expenses today'));
        $response->assertSee(__('Add expense'));
        $response->assertSee('x-model="closingNote"', false);
    }
}
