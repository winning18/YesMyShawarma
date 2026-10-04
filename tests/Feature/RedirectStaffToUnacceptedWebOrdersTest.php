<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RedirectStaffToUnacceptedWebOrdersTest extends TestCase
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

    private function unacceptedWebOrder(): Order
    {
        $customer = Customer::create(['phone' => '+233241111111']);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'paystack',
            'payment_status' => 'paid',
            'channel' => 'web',
        ]);
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_staffs_first_page_load_redirects_to_dashboard_when_web_orders_are_unaccepted(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($staff)->get(route('dashboard.pos.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_check_only_runs_once_per_session(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($staff)->get(route('dashboard.pos.index'))->assertRedirect(route('dashboard'));

        // Second visit to POS in the same session goes straight through —
        // this must never become a persistent nag mid-shift.
        $this->actingAs($staff)->get(route('dashboard.pos.index'))->assertOk();
    }

    public function test_no_redirect_when_there_are_no_unaccepted_web_orders(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.pos.index'))->assertOk();
    }

    public function test_a_pos_channel_order_does_not_trigger_the_redirect(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $order = $this->unacceptedWebOrder();
        $order->update(['channel' => 'pos']);

        $this->actingAs($staff)->get(route('dashboard.pos.index'))->assertOk();
    }

    public function test_manager_is_not_redirected(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($manager)->get(route('dashboard.pos.index'))->assertOk();
    }

    public function test_ajax_endpoints_are_never_redirected(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        // The dashboard's own polling/action endpoints must never get
        // redirected out from under themselves, nor consume the
        // once-per-session check before a real page load does.
        $this->actingAs($staff)->getJson(route('dashboard.orders.data'))->assertOk();

        $this->actingAs($staff)->get(route('dashboard.pos.index'))->assertRedirect(route('dashboard'));
    }

    public function test_visiting_dashboard_first_does_not_redirect_again(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('dashboard.pos.index'))->assertOk();
    }

    /**
     * Regression: a multi-branch staff member can now land directly on any
     * of these as their very first navigation of a session (picking a
     * branch no longer forces a detour through Dashboard first) — none of
     * them require a shift, so none of them may get hijacked away to
     * Dashboard just because a web order happens to be waiting.
     */
    public function test_order_history_is_never_hijacked_even_as_the_first_page_load(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($staff)->get(route('dashboard.orders.history'))->assertOk();
    }

    public function test_damage_reports_is_never_hijacked_even_as_the_first_page_load(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($staff)->get(route('dashboard.damage-reports.index'))->assertOk();
    }

    public function test_refunds_is_never_hijacked_even_as_the_first_page_load(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($staff)->get(route('dashboard.refunds.index'))->assertOk();
    }

    public function test_detailed_reports_is_never_hijacked_even_as_the_first_page_load(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        // A fresh session each time — the once-per-session flag would
        // otherwise already be consumed by a prior call in this same test,
        // making a second assertion here meaningless.
        $this->actingAs($staff)->get(route('dashboard.reports.index'))->assertOk();
    }

    public function test_sales_report_is_never_hijacked_even_as_the_first_page_load(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $this->unacceptedWebOrder();

        $this->actingAs($staff)->get(route('dashboard.reports.today.index'))->assertOk();
    }
}
