<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "POS/Orders and Start shift are not owner features. The dashboard of the
 * owner/manager account should show overall summary..." — covers the
 * routing/nav consequences of that rule, complementing the behavioural
 * assertions already in PerformanceTest, ShiftTest, PosOrderTest, and
 * BranchContextTest.
 */
class DashboardOverviewTest extends TestCase
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

    public function test_owner_dashboard_redirects_to_the_business_overview(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertRedirect(route('dashboard.performance'));
    }

    public function test_manager_dashboard_redirects_to_the_business_overview(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertRedirect(route('dashboard.performance'));
    }

    public function test_staff_dashboard_is_unaffected_still_the_live_board(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    }

    public function test_manager_can_reach_the_live_board_via_the_orders_route(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.orders.live'))->assertOk();
    }

    public function test_live_board_arranges_order_cards_in_a_grid_with_a_clickable_phone_link(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        // Order cards render client-side from JSON (orderDashboard()), but
        // the <template x-for="order in ..."> markup itself — including
        // the grid wrapper and the tel: link binding — is part of the
        // static Blade output, so it's assertable without a live browser.
        $response = $this->actingAs($manager)->get(route('dashboard.orders.live'));

        $response->assertOk();
        $response->assertSee('grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4', false);
        $response->assertSee(":href=\"'tel:' + order.customer_phone\"", false);
    }

    public function test_live_board_renders_order_items_in_both_needs_acknowledgement_and_in_progress_sections(): void
    {
        // Regression: "Needs acknowledgement" always showed items, but
        // "In progress" (accepted/preparing/ready/dispatched) never did —
        // this asserts the items <template x-for> markup appears twice,
        // once per section, not just once.
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $content = $this->actingAs($manager)->get(route('dashboard.orders.live'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($content, 'x-for="item in order.items"'));
    }

    public function test_live_board_shows_a_payment_method_badge_on_both_sections(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $content = $this->actingAs($manager)->get(route('dashboard.orders.live'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($content, 'x-text="paymentLabel(order)"'));
    }

    public function test_orders_nav_link_shows_for_manager_only(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $this->actingAs($manager)->get(route('dashboard.performance'))->assertSee('href="'.route('dashboard.orders.live').'"', false);
        $this->actingAs($staff)->get(route('dashboard'))->assertDontSee('href="'.route('dashboard.orders.live').'"', false);
        $this->actingAs($owner)->get(route('dashboard.performance'))->assertDontSee('href="'.route('dashboard.orders.live').'"', false);
    }

    public function test_standalone_performance_nav_link_no_longer_exists(): void
    {
        // The content still lives at dashboard.performance — it's just
        // reached via "Dashboard" now (route('dashboard') redirects
        // server-side), not a second nav entry linking straight there.
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $this->actingAs($owner)->get(route('dashboard.performance'))
            ->assertOk()
            ->assertDontSee('href="'.route('dashboard.performance').'"', false);
    }

    public function test_owner_viewing_order_history_sees_no_orders_pos_toggle_or_shift_widget(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $response = $this->actingAs($owner)->get(route('dashboard.orders.history'))->assertOk();

        // "POS" also legitimately appears in the always-visible "Order
        // History > Web/POS" nav dropdown — the channel-header's own
        // toggle button is what must be gone, so check its specific href.
        $response->assertDontSee('href="'.route('dashboard.pos.index').'"', false);
        $response->assertDontSee(__('Start shift'));
    }

    public function test_manager_viewing_order_history_still_sees_the_toggle_and_shift_widget(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $response = $this->actingAs($manager)->get(route('dashboard.orders.history'))->assertOk();

        $response->assertSee('href="'.route('dashboard.pos.index').'"', false);
    }
}
