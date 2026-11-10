<?php

namespace Tests\Feature;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Accounts\AccountSuspensionService;
use App\Services\Branches\BranchContext;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $osu;

    private Branch $labone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $make = fn (string $name, string $slug) => Branch::create([
            'name' => $name, 'slug' => $slug, 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->osu = $make('Osu', 'osu');
        $this->labone = $make('Labone', 'labone');
    }

    private function userAt(string $role, Branch $branch, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function suspensions(): AccountSuspensionService
    {
        return app(AccountSuspensionService::class);
    }

    public function test_a_suspended_staff_member_is_signed_out_and_cannot_log_back_in_until_lifted(): void
    {
        $owner = $this->userAt('owner', $this->osu);
        $staff = $this->userAt('staff', $this->osu, ['email' => 'kofi@example.com', 'password' => 'Secret-pass-123']);

        $this->actingAs($staff)->withSession(['current_branch_id' => $this->osu->id])->get(route('dashboard.orders.history'))->assertOk();

        $this->actingAs($owner)->withSession(['current_branch_id' => $this->osu->id])
            ->post(route('dashboard.users.suspend', $staff), ['reason' => 'Cash shortages'])->assertSessionHasNoErrors();
        $this->assertNotNull($staff->fresh()->suspended_at);
        $this->assertSame('Cash shortages', $staff->fresh()->suspension_reason);

        $this->actingAs($staff->fresh())->get(route('dashboard.orders.history'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your account is suspended. Please contact the branch manager.']);
        $this->assertGuest('web');

        $this->post('/login', ['email' => 'kofi@example.com', 'password' => 'Secret-pass-123'])
            ->assertSessionHasErrors(['email' => 'Your account is suspended. Please contact the branch manager.']);
        $this->assertGuest('web');

        $this->actingAs($owner)->post(route('dashboard.users.unsuspend', $staff))->assertSessionHasNoErrors();
        auth('web')->logout();
        $this->post('/login', ['email' => 'kofi@example.com', 'password' => 'Secret-pass-123'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($staff->fresh(), 'web');
    }

    public function test_a_suspended_rider_cannot_log_in_and_gets_no_deliveries_or_alerts(): void
    {
        $owner = $this->userAt('owner', $this->osu);
        $rider = $this->userAt('rider', $this->osu, ['email' => 'rider@example.com', 'password' => 'Secret-pass-123']);
        $this->suspensions()->suspend($rider, $owner, 'Repeated late deliveries');

        $this->post(route('rider.login'), ['login' => 'rider@example.com', 'password' => 'Secret-pass-123'])
            ->assertSessionHasErrors('login');
        $this->assertGuest('rider');

        $this->assertFalse(app(BranchContext::class)->usersWithRole('rider', $this->osu->id)->contains('id', $rider->id));
    }

    public function test_a_suspended_customer_cannot_log_in_register_again_or_order_online_but_the_pos_still_can(): void
    {
        $owner = $this->userAt('owner', $this->osu);
        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama', 'password' => 'Secret-pass-123']);
        $this->suspensions()->suspend($customer, $owner, 'Fake orders');

        $this->post(route('customer.login'), ['phone' => '0241111111', 'password' => 'Secret-pass-123'])
            ->assertSessionHasErrors(['phone' => 'This account is suspended. Please call the branch.']);
        $this->assertGuest('customer');

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $this->osu->menuItems()->attach($item->id, ['is_available' => true]);
        $order = fn (string $channel) => app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111', customerName: 'Ama', branchId: $this->osu->id,
            fulfilmentType: 'pickup', paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $item->id, quantity: 1)], channel: $channel,
        ));

        try {
            $order('web');
            $this->fail('A suspended customer placed a web order.');
        } catch (OrderPlacementException $e) {
            $this->assertSame("This number can't place orders online. Please call the branch.", $e->getMessage());
        }

        $this->assertSame('pos', $order('pos')->channel);
    }

    public function test_a_general_manager_can_suspend_only_staff_and_riders_at_their_own_branches(): void
    {
        $gm = $this->userAt('general_manager', $this->osu);
        $ownStaff = $this->userAt('staff', $this->osu);
        $otherStaff = $this->userAt('staff', $this->labone);
        $manager = $this->userAt('manager', $this->osu);

        $this->assertTrue($this->suspensions()->canSuspendUser($gm, $ownStaff));
        $this->assertFalse($this->suspensions()->canSuspendUser($gm, $otherStaff));
        $this->assertFalse($this->suspensions()->canSuspendUser($gm, $manager));
        $this->assertTrue($this->suspensions()->canSuspendCustomers($gm));

        $this->actingAs($gm)->withSession(['current_branch_id' => $this->osu->id])
            ->post(route('dashboard.users.suspend', $otherStaff), ['reason' => 'x'])->assertForbidden();
        $this->assertNull($otherStaff->fresh()->suspended_at);
    }

    public function test_nobody_can_suspend_an_owner_or_themselves_and_managers_cannot_suspend_at_all(): void
    {
        $owner = $this->userAt('owner', $this->osu);
        $secondOwner = $this->userAt('owner', $this->osu);
        $manager = $this->userAt('manager', $this->osu);
        $staff = $this->userAt('staff', $this->osu);

        $this->assertFalse($this->suspensions()->canSuspendUser($owner, $owner));
        $this->assertFalse($this->suspensions()->canSuspendUser($owner, $secondOwner));
        $this->assertFalse($this->suspensions()->canSuspendUser($manager, $staff));
        $this->assertFalse($this->suspensions()->canSuspendCustomers($manager));

        $customer = Customer::create(['phone' => '+233242222222']);
        $this->actingAs($manager)->withSession(['current_branch_id' => $this->osu->id])
            ->post(route('dashboard.customers.suspend', $customer), ['reason' => 'x'])->assertForbidden();
    }

    public function test_the_screens_show_suspend_and_lift_only_to_those_allowed(): void
    {
        $owner = $this->userAt('owner', $this->osu);
        $staff = $this->userAt('staff', $this->osu);
        Customer::create(['phone' => '+233243333333', 'name' => 'Yaw']);
        $session = ['current_branch_id' => $this->osu->id];

        $this->actingAs($owner)->withSession($session)->get(route('dashboard.users.edit', $staff))->assertSee('Suspend account');
        $this->actingAs($owner)->withSession($session)->get(route('dashboard.customers.index'))->assertSee('Suspend');

        $this->suspensions()->suspend($staff, $owner, 'Investigation');
        $this->actingAs($owner)->withSession($session)->get(route('dashboard.users.edit', $staff))->assertSee('Lift suspension')->assertSee('Investigation');
        $this->actingAs($owner)->withSession($session)->get(route('dashboard.users.index'))->assertSee('Suspended');

        $manager = $this->userAt('manager', $this->osu);
        $this->actingAs($manager)->withSession($session)->get(route('dashboard.customers.index'))->assertDontSee('Lift suspension')->assertDontSee('>Suspend<', false);
    }
}
