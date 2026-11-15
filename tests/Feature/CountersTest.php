<?php

namespace Tests\Feature;

use App\Exceptions\OrderPlacementException;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Shifts\CounterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Express POS step 7a: several cashiers as named counters, each with a till
 * of its own. A branch with no counters keeps its one shared shift.
 */
class CountersTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Wraps', 'slug' => 'wraps'])->id,
            'name' => 'Chicken Wrap', 'slug' => 'chicken-wrap', 'base_price' => 3000,
        ]);
        $this->branch->menuItems()->attach($this->item->id, ['is_available' => true]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $this->branch->id]);
    }

    /** This test's "device" becomes the counter. */
    private function useDevice(Terminal $counter): void
    {
        $this->defaultCookies = [];
        // Browsers send it with every same-site request, the POS's fetch() calls included.
        $this->withCredentials()->withCookie(CounterService::COOKIE, app(CounterService::class)->pair($counter)->getValue());
    }

    private function sell(User $staff, int $quantity = 1)
    {
        $this->as($staff)->postJson(route('dashboard.pos.cart.add'), ['menu_item_id' => $this->item->id, 'quantity' => $quantity, 'option_ids' => []])->assertOk();

        return $this->as($staff)->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', 'payment_method' => 'cash']);
    }

    public function test_a_branch_without_counters_keeps_its_one_shared_shift(): void
    {
        $first = $this->userWithRole('staff');
        $second = $this->userWithRole('staff');

        $this->as($first)->postJson(route('shift.start'), ['starting_cash' => '50'])->assertOk();
        $this->as($second)->postJson(route('shift.start'))->assertOk();
        $this->assertSame(1, Shift::count());
        $this->assertNull(Shift::sole()->terminal_id);

        $this->sell($second)->assertOk();
        $this->assertSame(Shift::sole()->id, Order::sole()->shift_id);
    }

    public function test_counters_are_managed_by_managers_not_staff(): void
    {
        $this->as($this->userWithRole('staff'))->get(route('dashboard.counters.index'))->assertForbidden();

        $manager = $this->userWithRole('manager');
        $this->as($manager)->post(route('dashboard.counters.store'), ['name' => 'Counter 1'])->assertSessionHas('status');
        $this->as($manager)->post(route('dashboard.counters.store'), ['name' => 'Counter 1'])->assertSessionHasErrors('name');
        $this->as($manager)->get(route('dashboard.counters.index'))->assertOk()->assertSee('Counter 1')->assertSee('No device yet');
    }

    public function test_the_first_counter_waits_for_the_shared_shift_to_close(): void
    {
        $this->as($this->userWithRole('staff'))->postJson(route('shift.start'))->assertOk();

        $this->as($this->userWithRole('manager'))->post(route('dashboard.counters.store'), ['name' => 'Counter 1'])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, Terminal::count());
    }

    public function test_with_counters_pos_sales_are_only_taken_on_a_counter(): void
    {
        Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
        $staff = $this->userWithRole('staff');

        $this->as($staff)->get(route('dashboard.pos.index'))->assertOk()->assertSee('This device isn\'t a counter');
        $this->as($staff)->postJson(route('shift.start'))->assertUnprocessable();
        $this->sell($staff)->assertUnprocessable();

        $this->expectException(OrderPlacementException::class);
        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: null, customerName: null, branchId: $this->branch->id, fulfilmentType: 'pickup',
            paymentMethod: 'cash', items: [new PlaceOrderItemData(menuItemId: $this->item->id, quantity: 1)], channel: 'pos',
        ));
    }

    public function test_each_counter_has_its_own_till_float_sales_and_cash_up(): void
    {
        $counterOne = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
        $counterTwo = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 2']);
        $ama = $this->userWithRole('staff');
        $kofi = $this->userWithRole('staff');

        $this->useDevice($counterOne);
        $this->as($ama)->postJson(route('shift.start'), ['starting_cash' => '100'])->assertOk();
        $this->sell($ama, 2)->assertOk();
        $tillOne = Shift::where('terminal_id', $counterOne->id)->sole();

        $this->useDevice($counterTwo);
        $this->as($kofi)->postJson(route('shift.start'), ['starting_cash' => '50'])->assertOk();
        $this->sell($kofi, 1)->assertOk();
        $tillTwo = Shift::where('terminal_id', $counterTwo->id)->sole();

        // Two tills open at once, each with its own float and orders.
        $this->assertSame([10000, 5000], [$tillOne->starting_cash, $tillTwo->starting_cash]);
        $this->assertSame([1, 1], [Order::where('shift_id', $tillOne->id)->count(), Order::where('shift_id', $tillTwo->id)->count()]);
        $this->as($kofi)->getJson(route('shift.show'))->assertJsonPath('system_sales', 3000)->assertJsonPath('branch', 'Osu · Counter 2');

        // Counter 2's cash-up is checked against its own GH₵30, not the branch's GH₵90.
        $this->as($kofi)->postJson(route('shift.end'), ['total_sales' => '20', 'no_expenses' => true])->assertUnprocessable();
        $this->as($kofi)->postJson(route('shift.end'), ['total_sales' => '30', 'no_expenses' => true])->assertOk();
        $this->assertSame(3000, $tillTwo->fresh()->system_sales);
        $this->assertNull($tillOne->fresh()->ended_at, "Counter 1's till stays open");
    }

    public function test_a_web_order_goes_in_the_till_that_accepts_it(): void
    {
        $counter = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
        $staff = $this->userWithRole('staff');
        $this->useDevice($counter);
        $this->as($staff)->postJson(route('shift.start'))->assertOk();

        $order = Order::create([
            'reference' => 'WEB-1', 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => Customer::create(['phone' => '+233240000001'])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 3000, 'total' => 3000,
            'payment_method' => 'cash', 'payment_status' => 'pending', 'channel' => 'web',
        ]);
        $order->forceFill(['status' => 'paid', 'placed_at' => now()])->save();

        $this->as($staff)->postJson(route('orders.accept', $order))->assertOk();

        $this->assertSame(Shift::sole()->id, $order->fresh()->shift_id);
    }

    public function test_a_device_can_be_moved_off_a_counter(): void
    {
        $counter = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
        $manager = $this->userWithRole('manager');

        $this->useDevice($counter);
        $this->as($manager)->get(route('dashboard.counters.index'))->assertSee('This device:')->assertSee('Counter 1 (Osu)');

        $this->as($manager)->post(route('dashboard.counters.forget'))->assertSessionHas('status');
        $this->assertFalse($counter->fresh()->isPaired());
        $this->as($manager)->get(route('dashboard.counters.index'))->assertSee('not a counter');
    }

    public function test_a_counter_with_an_open_till_cant_be_switched_off(): void
    {
        $counter = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
        $this->useDevice($counter);
        $this->as($this->userWithRole('staff'))->postJson(route('shift.start'))->assertOk();

        $this->as($this->userWithRole('manager'))->put(route('dashboard.counters.update', $counter), ['name' => 'Counter 1'])
            ->assertSessionHasErrors('counter');
        $this->assertTrue($counter->fresh()->is_active);
    }

    public function test_the_today_report_names_each_till_s_counter(): void
    {
        $counter = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Front Counter']);
        $this->useDevice($counter);
        $this->as($this->userWithRole('staff'))->postJson(route('shift.start'))->assertOk();

        $this->as($this->userWithRole('owner'))->get(route('dashboard.reports.today.index', ['branch' => $this->branch->id]))
            ->assertOk()->assertSee('Front Counter');
    }

    public function test_staff_on_a_device_that_isnt_a_counter_are_not_stuck_behind_open_till(): void
    {
        Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
        $staff = $this->userWithRole('staff');

        $this->as($staff)->get(route('dashboard'))->assertOk()
            ->assertViewHas('forceShiftStart', false)
            ->assertSee('Not a counter');
    }

    public function test_the_today_report_for_one_till_shows_only_that_till_s_sales(): void
    {
        $counterOne = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 1']);
        $counterTwo = Terminal::create(['branch_id' => $this->branch->id, 'name' => 'Counter 2']);
        $ama = $this->userWithRole('staff');
        $kofi = $this->userWithRole('staff');

        $this->useDevice($counterOne);
        $this->as($ama)->postJson(route('shift.start'))->assertOk();
        $this->sell($ama, 2)->assertOk();

        $this->useDevice($counterTwo);
        $this->as($kofi)->postJson(route('shift.start'))->assertOk();
        $this->sell($kofi, 1)->assertOk();
        $tillTwo = Shift::where('terminal_id', $counterTwo->id)->sole();

        $this->as($this->userWithRole('owner'))
            ->get(route('dashboard.reports.today.index', ['branch' => $this->branch->id, 'shift' => $tillTwo->id]))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary) => $summary['total_sales'] === 3000 && $summary['orders_count'] === 1);
    }
}
