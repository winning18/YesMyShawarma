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

class ReportBranchFilterTest extends TestCase
{
    use RefreshDatabase;

    private Branch $osu;

    private Branch $labone;

    private Branch $tema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->osu = $this->branch('Osu');
        $this->labone = $this->branch('Labone');
        $this->tema = $this->branch('Tema');

        $this->order($this->osu, 1000);
        $this->order($this->labone, 2000);
        $this->order($this->tema, 4000);
    }

    private function branch(string $name): Branch
    {
        return Branch::create([
            'name' => $name, 'slug' => strtolower($name), 'phone' => '+23320000000'.random_int(0, 9), 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function order(Branch $branch, int $total): void
    {
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(), 'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)])->id,
            'branch_id' => $branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => $total, 'total' => $total,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'channel' => 'pos',
        ]);
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();
    }

    private function userAt(string $role, Branch ...$branches): User
    {
        $user = User::factory()->create();

        foreach ($branches as $branch) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
            $user->assignRole($role);
        }

        return $user;
    }

    private function as(User $user, Branch $current)
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $current->id]);
    }

    private function today(): array
    {
        return ['date' => now('Africa/Accra')->toDateString(), 'channel' => 'pos'];
    }

    public function test_a_manager_sees_only_their_assigned_branches_in_the_filter_on_every_tab(): void
    {
        $manager = $this->userAt('manager', $this->osu, $this->labone);

        foreach (['dashboard.reports.index', 'dashboard.reports.today.index', 'dashboard.reports.invoices.index', 'dashboard.reports.weekly.index', 'dashboard.reports.expenses.index', 'dashboard.reports.delivery-fees.index'] as $route) {
            $response = $this->as($manager, $this->osu)->get(route($route))->assertOk();

            $this->assertSame(['Labone', 'Osu'], $response->viewData('branchOptions')->pluck('name')->all(), $route);
            $response->assertSee('All my branches');
        }
    }

    public function test_a_manager_with_one_branch_sees_just_that_branch(): void
    {
        $manager = $this->userAt('manager', $this->osu);

        $response = $this->as($manager, $this->osu)->get(route('dashboard.reports.index'))->assertOk();

        $this->assertSame(['Osu'], $response->viewData('branchOptions')->pluck('name')->all());
    }

    public function test_staff_get_no_filter(): void
    {
        $staff = $this->userAt('staff', $this->osu);

        foreach (['dashboard.reports.index', 'dashboard.reports.today.index'] as $route) {
            $response = $this->as($staff, $this->osu)->get(route($route))->assertOk();
            $this->assertFalse($response->viewData('crossBranch'));
            $response->assertDontSee('id="branch"', false);
        }
    }

    public function test_detailed_reports_default_to_a_managers_own_branches_and_narrow_to_one(): void
    {
        $manager = $this->userAt('manager', $this->osu, $this->labone);

        $all = $this->as($manager, $this->osu)->get(route('dashboard.reports.index'));
        $this->assertSame(3000, $all->viewData('financial')['revenue_total']);

        $one = $this->as($manager, $this->osu)->get(route('dashboard.reports.index', ['branch' => $this->labone->id]));
        $this->assertSame(2000, $one->viewData('financial')['revenue_total']);
    }

    public function test_a_manager_cannot_reach_a_branch_outside_their_own_by_editing_the_link(): void
    {
        $manager = $this->userAt('manager', $this->osu, $this->labone);

        $detailed = $this->as($manager, $this->osu)->get(route('dashboard.reports.index', ['branch' => $this->tema->id]));
        $this->assertSame(3000, $detailed->viewData('financial')['revenue_total']);
        $this->assertNull($detailed->viewData('branchFilterId'));

        $sales = $this->as($manager, $this->osu)->get(route('dashboard.reports.today.index', [...$this->today(), 'branch' => $this->tema->id]));
        $this->assertNull($sales->viewData('viewingBranch'));
        $this->assertSame(1000, $sales->viewData('summary')['total_sales']);
    }

    public function test_sales_tab_defaults_to_the_current_branch_and_all_covers_only_a_managers_own(): void
    {
        $manager = $this->userAt('manager', $this->osu, $this->labone);

        $default = $this->as($manager, $this->osu)->get(route('dashboard.reports.today.index', $this->today()));
        $this->assertSame(1000, $default->viewData('summary')['total_sales']);

        $all = $this->as($manager, $this->osu)->get(route('dashboard.reports.today.index', [...$this->today(), 'branch' => 'all']));
        $this->assertSame(3000, $all->viewData('summary')['total_sales']);

        $one = $this->as($manager, $this->osu)->get(route('dashboard.reports.today.index', [...$this->today(), 'branch' => $this->labone->id]));
        $this->assertSame(2000, $one->viewData('summary')['total_sales']);
    }

    public function test_a_general_manager_filters_across_their_own_branches_only(): void
    {
        $generalManager = $this->userAt('general_manager', $this->labone, $this->tema);

        $all = $this->as($generalManager, $this->labone)->get(route('dashboard.reports.today.index', [...$this->today(), 'branch' => 'all']));

        $this->assertSame(6000, $all->viewData('summary')['total_sales']);
        $this->assertSame(['Labone', 'Tema'], $all->viewData('branchOptions')->pluck('name')->all());
    }

    public function test_the_owner_can_pick_any_branch_or_all_even_with_one_selected_in_session(): void
    {
        $owner = $this->userAt('owner', $this->osu);

        $all = $this->as($owner, $this->osu)->get(route('dashboard.reports.today.index', [...$this->today(), 'branch' => 'all']));
        $this->assertSame(7000, $all->viewData('summary')['total_sales']);
        $all->assertSee('All branches');

        $one = $this->as($owner, $this->osu)->get(route('dashboard.reports.today.index', [...$this->today(), 'branch' => $this->tema->id]));
        $this->assertSame(4000, $one->viewData('summary')['total_sales']);
    }

    public function test_downloads_follow_a_managers_branches_too(): void
    {
        $manager = $this->userAt('manager', $this->osu, $this->labone);

        $csv = $this->as($manager, $this->osu)->get(route('dashboard.reports.weekly.download', ['branch' => $this->tema->id]))->assertOk()->streamedContent();

        $this->assertSame(3, substr_count($csv, "\n")); // header + Osu + Labone, Tema dropped
    }
}
