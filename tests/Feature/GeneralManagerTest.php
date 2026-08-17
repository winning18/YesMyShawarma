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

/**
 * general_manager = manager's full permission set, applied one-branch-at-
 * a-time via the branch switcher like any manager, PLUS two things a
 * plain manager doesn't get: a multi-branch aggregate on the Performance
 * page (scoped to only the branches they hold general_manager at — never
 * "all branches"), and the ability to create staff/rider accounts.
 */
class GeneralManagerTest extends TestCase
{
    use RefreshDatabase;

    private Branch $osu;

    private Branch $eastLegon;

    private Branch $spintex;

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
        $this->spintex = Branch::create([
            'name' => 'Spintex', 'slug' => 'spintex', 'phone' => '+233200000003', 'address' => 'C',
            'lat' => 5.7, 'lng' => -0.3, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    /**
     * General manager of Osu and East Legon only — Spintex is deliberately
     * left out so every test can assert it never leaks in. Holding two
     * branches makes this a "multi-branch" account exactly like a
     * multi-branch manager (BranchContextTest) — ResolveCurrentBranch
     * forces it through branch selection before any 'branch'-gated route
     * works at all, so every test picks one (it doesn't matter which —
     * the performance aggregate never depends on the session branch)
     * before exercising the actual behaviour under test.
     */
    private function makeGeneralManager(): User
    {
        $gm = User::factory()->create();
        $this->assignRoleAt($gm, 'general_manager', $this->osu);
        $this->assignRoleAt($gm, 'general_manager', $this->eastLegon);

        $this->actingAs($gm)->post(route('branches.select.store'), ['branch_id' => $this->osu->id]);

        return $gm;
    }

    private function makeOrder(Branch $branch, string $status, int $total): Order
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
            'channel' => 'web',
        ]);
        $order->status = $status;
        $order->placed_at = now('Africa/Accra');
        $order->save();

        return $order;
    }

    public function test_general_manager_dashboard_redirects_to_the_business_overview(): void
    {
        $gm = $this->makeGeneralManager();

        $this->actingAs($gm)->get(route('dashboard'))
            ->assertRedirect(route('dashboard.performance'));
    }

    public function test_general_manager_reaches_the_live_board_via_the_orders_route(): void
    {
        $gm = $this->makeGeneralManager();

        $this->actingAs($gm)->get(route('dashboard.orders.live'))->assertOk();
    }

    public function test_performance_aggregates_only_the_general_managers_own_branches(): void
    {
        $gm = $this->makeGeneralManager();

        $this->makeOrder($this->osu, 'delivered', 5000);
        $this->makeOrder($this->eastLegon, 'delivered', 3000);
        $this->makeOrder($this->spintex, 'delivered', 100000); // not theirs — must never count

        $response = $this->actingAs($gm)
            ->get(route('dashboard.performance', ['tab' => 'sales', 'range' => 'today']));

        $summary = $response->viewData('summary');

        $this->assertSame(8000, $summary['sales']['value']);
        $this->assertSame(2, $summary['orders']['value']);
    }

    public function test_by_branch_breakdown_lists_only_the_general_managers_own_branches(): void
    {
        $gm = $this->makeGeneralManager();

        $response = $this->actingAs($gm)
            ->get(route('dashboard.performance', ['tab' => 'operations', 'range' => 'today']));

        $branches = $response->viewData('branches');

        $this->assertCount(2, $branches);
        $this->assertTrue($branches->contains(fn ($row) => $row['branch']->id === $this->osu->id));
        $this->assertTrue($branches->contains(fn ($row) => $row['branch']->id === $this->eastLegon->id));
        $this->assertFalse($branches->contains(fn ($row) => $row['branch']->id === $this->spintex->id));
    }

    public function test_general_manager_can_filter_operations_to_one_of_their_own_branches(): void
    {
        $gm = $this->makeGeneralManager();

        $this->makeOrder($this->eastLegon, 'delivered', 3000);
        $this->makeOrder($this->eastLegon, 'delivered', 3000);

        $response = $this->actingAs($gm)->get(route('dashboard.performance', [
            'tab' => 'operations', 'range' => 'today', 'branch' => $this->eastLegon->id,
        ]));

        $this->assertSame(2, $response->viewData('operational')['total_orders']);
        $this->assertNull($response->viewData('branches'));
    }

    public function test_general_manager_cannot_use_the_branch_filter_to_peek_at_a_branch_they_dont_oversee(): void
    {
        $gm = $this->makeGeneralManager();

        $this->makeOrder($this->osu, 'delivered', 5000);
        $this->makeOrder($this->eastLegon, 'delivered', 3000);
        $this->makeOrder($this->spintex, 'delivered', 100000);

        $response = $this->actingAs($gm)->get(route('dashboard.performance', [
            'tab' => 'operations', 'range' => 'today', 'branch' => $this->spintex->id,
        ]));

        // Tampered value is silently ignored — falls back to the
        // general_manager's own aggregate rather than exposing Spintex.
        $this->assertSame(2, $response->viewData('operational')['total_orders']);
        $this->assertNotNull($response->viewData('branches'));
    }

    public function test_general_manager_can_create_a_staff_user_at_a_branch_they_oversee(): void
    {
        $gm = $this->makeGeneralManager();

        $response = $this->actingAs($gm)->post(route('dashboard.users.store'), [
            'name' => 'New Staff', 'email' => 'newstaff@example.com', 'role' => 'staff', 'branch_id' => $this->osu->id,
        ]);

        $newUser = User::where('email', 'newstaff@example.com')->first();
        $this->assertNotNull($newUser);
        $response->assertRedirect(route('dashboard.users.edit', $newUser));

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->osu->id);
        $this->assertTrue($newUser->hasRole('staff'));
    }

    public function test_general_manager_can_create_a_rider_user(): void
    {
        $gm = $this->makeGeneralManager();

        $this->actingAs($gm)->post(route('dashboard.users.store'), [
            'name' => 'New Rider', 'email' => 'newrider@example.com', 'role' => 'rider', 'branch_id' => $this->eastLegon->id,
        ])->assertRedirect();

        $newUser = User::where('email', 'newrider@example.com')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->eastLegon->id);
        $this->assertTrue($newUser->fresh()->hasRole('rider'));
    }

    public function test_general_manager_cannot_create_a_user_at_a_branch_they_dont_oversee(): void
    {
        $gm = $this->makeGeneralManager();

        $this->actingAs($gm)->post(route('dashboard.users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'staff', 'branch_id' => $this->spintex->id,
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'sneaky@example.com')->first());
    }

    public function test_general_manager_cannot_create_a_manager_user(): void
    {
        $gm = $this->makeGeneralManager();

        $this->actingAs($gm)->post(route('dashboard.users.store'), [
            'name' => 'Sneaky Manager', 'email' => 'sneakymanager@example.com', 'role' => 'manager', 'branch_id' => $this->osu->id,
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'sneakymanager@example.com')->first());
    }

    public function test_general_manager_cannot_create_another_general_manager(): void
    {
        $gm = $this->makeGeneralManager();

        $this->actingAs($gm)->post(route('dashboard.users.store'), [
            'name' => 'Sneaky GM', 'email' => 'sneakygm@example.com', 'role' => 'general_manager', 'branch_id' => $this->osu->id,
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'sneakygm@example.com')->first());
    }

    public function test_general_manager_cannot_create_an_owner(): void
    {
        $gm = $this->makeGeneralManager();

        $this->actingAs($gm)->post(route('dashboard.users.store'), [
            'name' => 'Sneaky Owner', 'email' => 'sneakyowner@example.com', 'role' => 'owner', 'branch_id' => $this->osu->id,
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'sneakyowner@example.com')->first());
    }

    public function test_general_manager_cannot_delete_a_user(): void
    {
        $gm = $this->makeGeneralManager();
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->osu);

        $this->actingAs($gm)->delete(route('dashboard.users.destroy', $staff))->assertForbidden();
    }

    public function test_general_manager_cannot_add_a_role_to_an_existing_user(): void
    {
        $gm = $this->makeGeneralManager();
        $user = User::factory()->create();

        $this->actingAs($gm)->post(route('dashboard.users.roles.add', $user), [
            'role' => 'staff', 'branch_id' => $this->osu->id,
        ])->assertForbidden();
    }

    public function test_general_managers_own_role_cannot_be_transferred_by_a_plain_manager(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);

        $gm = $this->makeGeneralManager();

        $this->actingAs($manager)->post(route('dashboard.users.change_branch', $gm), [
            'role' => 'general_manager',
            'from_branch_id' => $this->osu->id,
            'to_branch_id' => $this->spintex->id,
        ])->assertForbidden();
    }

    public function test_owner_can_transfer_a_general_managers_branch(): void
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->osu);

        $gm = $this->makeGeneralManager();

        $this->actingAs($owner)->post(route('dashboard.users.change_branch', $gm), [
            'role' => 'general_manager',
            'from_branch_id' => $this->osu->id,
            'to_branch_id' => $this->spintex->id,
        ])->assertRedirect();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->spintex->id);
        $this->assertTrue($gm->fresh()->hasRole('general_manager'));
    }

    public function test_plain_manager_still_cannot_create_users_at_all(): void
    {
        // Regression guard — general_manager's new create ability must
        // never leak onto the plain manager role it's built alongside.
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->osu);

        $this->actingAs($manager)->get(route('dashboard.users.create'))->assertForbidden();

        $this->actingAs($manager)->post(route('dashboard.users.store'), [
            'name' => 'Someone', 'email' => 'someone@example.com', 'role' => 'staff', 'branch_id' => $this->osu->id,
        ])->assertForbidden();
    }
}
