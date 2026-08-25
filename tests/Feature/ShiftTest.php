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

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '250.00'])->assertOk();

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
        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '875.25'])->assertOk();

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'total_sales' => 87525,
        ]);
    }

    public function test_manager_can_end_a_shift_without_total_sales(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->postJson(route('shift.start'))->assertOk();
        $this->actingAs($manager)->postJson(route('shift.end'))->assertOk();

        $this->actingAs($manager)->getJson(route('shift.show'))->assertJsonPath('active', false);
    }

    public function test_staff_cannot_end_shift_reporting_less_than_todays_system_sales(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(10000); // GHS 100.00 recorded by the system

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '50.00'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => "Total sales cannot be less than today's recorded sales of GHS 100.00."]);

        $this->assertDatabaseHas('shifts', ['user_id' => $staff->id, 'ended_at' => null]);
    }

    public function test_staff_can_end_shift_reporting_exactly_todays_system_sales(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(10000);

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '100.00'])->assertOk();

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

        $this->actingAs($staff)->postJson(route('shift.end'), ['total_sales' => '130.00'])->assertOk();

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

    public function test_shift_show_omits_system_sales_for_manager(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);
        $this->actingAs($manager)->postJson(route('shift.start'))->assertOk();

        $this->revenueOrder(5000);

        $this->actingAs($manager)->getJson(route('shift.show'))
            ->assertOk()
            ->assertJsonPath('system_sales', null);
    }

    public function test_cannot_start_a_second_shift_while_one_is_active(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.start'))->assertUnprocessable();
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
}
