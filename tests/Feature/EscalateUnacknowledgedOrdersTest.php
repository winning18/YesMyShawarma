<?php

namespace Tests\Feature;

use App\Contracts\Notifier;
use App\Models\Branch;
use App\Models\BranchWorkingHour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EscalateUnacknowledgedOrdersTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeOrderPaidMinutesAgo(int $minutes): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        $order->status = 'paid';
        $order->placed_at = now()->subMinutes($minutes);
        $order->save();

        $event = $order->events()->create([
            'from_status' => null,
            'to_status' => 'paid',
            'actor_type' => 'customer',
            'actor_id' => $customer->id,
        ]);

        DB::table('order_events')->where('id', $event->id)->update([
            'created_at' => now()->subMinutes($minutes),
        ]);

        return $order;
    }

    public function test_escalates_to_manager_past_five_minutes(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $order = $this->makeOrderPaidMinutesAgo(6);

        $this->mock(Notifier::class, function ($mock) use ($manager) {
            $mock->shouldReceive('notify')->once()
                ->with(\Mockery::on(fn (User $u) => $u->id === $manager->id), \Mockery::type('string'), \Mockery::type('array'));
        });

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'meta->escalation_level' => 5,
        ]);
    }

    public function test_escalates_to_general_manager_past_five_minutes(): void
    {
        // general_manager holds everything a manager holds (permissions.md)
        // — including this notification — at every branch they oversee.
        $generalManager = User::factory()->create();
        $this->assignRoleAt($generalManager, 'general_manager', $this->branch);

        $order = $this->makeOrderPaidMinutesAgo(6);

        $this->mock(Notifier::class, function ($mock) use ($generalManager) {
            $mock->shouldReceive('notify')->once()
                ->with(\Mockery::on(fn (User $u) => $u->id === $generalManager->id), \Mockery::type('string'), \Mockery::type('array'));
        });

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'meta->escalation_level' => 5,
        ]);
    }

    public function test_escalates_to_both_manager_and_general_manager_when_both_oversee_the_branch(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $generalManager = User::factory()->create();
        $this->assignRoleAt($generalManager, 'general_manager', $this->branch);

        $this->makeOrderPaidMinutesAgo(6);

        $notified = [];
        $this->mock(Notifier::class, function ($mock) use (&$notified) {
            $mock->shouldReceive('notify')->twice()
                ->with(\Mockery::on(function (User $u) use (&$notified) {
                    $notified[] = $u->id;

                    return true;
                }), \Mockery::type('string'), \Mockery::type('array'));
        });

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$manager->id, $generalManager->id], $notified);
    }

    public function test_does_not_escalate_the_same_threshold_twice(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->makeOrderPaidMinutesAgo(6);

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();
        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertDatabaseCount('order_events', 2); // placed + 1 escalation, not 2 escalations
    }

    public function test_escalates_to_owner_past_ten_minutes(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $order = $this->makeOrderPaidMinutesAgo(11);

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'meta->escalation_level' => 5]);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'meta->escalation_level' => 10]);
    }

    public function test_does_not_escalate_before_threshold(): void
    {
        $this->makeOrderPaidMinutesAgo(2);

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertDatabaseCount('order_events', 1); // just the original "placed" event
    }

    public function test_does_not_escalate_while_the_branch_is_closed(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(3, 0));

        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);

        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $order = $this->makeOrderPaidMinutesAgo(30);

        $this->mock(Notifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertDatabaseCount('order_events', 1); // just the original "placed" event
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_escalates_immediately_once_the_branch_reopens_if_already_overdue(): void
    {
        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);

        // Placed overnight while closed, already 30 minutes old by the time
        // the branch's schedule says it should be open again.
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(3, 0));

        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $order = $this->makeOrderPaidMinutesAgo(0);

        // Just 3 minutes past reopening — if escalation waited out a fresh
        // countdown from reopening instead of judging total elapsed time,
        // this wouldn't have fired yet.
        Carbon::setTestNow(Carbon::now('Africa/Accra')->setTime(10, 3));

        $this->mock(Notifier::class, function ($mock) use ($manager) {
            $mock->shouldReceive('notify')->once()
                ->with(\Mockery::on(fn (User $u) => $u->id === $manager->id), \Mockery::type('string'), \Mockery::type('array'));
        });

        $this->artisan('orders:escalate-unacknowledged')->assertSuccessful();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'meta->escalation_level' => 5,
        ]);
    }
}
