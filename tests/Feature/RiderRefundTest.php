<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\Reports\DeliveryFeeReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RiderRefundTest extends TestCase
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

    private function userAt(string $role): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function delivered(User $rider, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'reference' => 'ORD-'.uniqid(), 'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)])->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'delivery',
            'subtotal' => 5000, 'delivery_fee' => 1500, 'total' => 6500,
            'payment_method' => 'cash', 'payment_status' => 'paid',
        ], $overrides));
        $order->status = $overrides['status'] ?? 'delivered';
        $order->rider_id = $rider->id;
        $order->delivered_at = $overrides['delivered_at'] ?? now()->subHour();
        $order->save();

        return $order;
    }

    private function asRider(User $rider)
    {
        return $this->actingAs($rider, 'rider')->withSession(['current_branch_id' => $this->branch->id]);
    }

    public function test_a_rider_requests_a_full_refund_on_their_own_recent_delivery(): void
    {
        $rider = $this->userAt('rider');
        $order = $this->delivered($rider);

        $this->asRider($rider)->post(route('rider.refunds.store', $order->id), ['reason' => 'Wrong order delivered'])
            ->assertSessionHasNoErrors();

        $refund = Refund::withoutGlobalScopes()->sole();
        $this->assertSame('pending', $refund->status);
        $this->assertSame(6500, $refund->amount); // food + delivery fee
        $this->assertSame($rider->id, $refund->requested_by);
        $this->assertTrue($order->events()->where('meta->action', 'refund_requested')->exists());
    }

    public function test_a_rider_cannot_request_for_someone_elses_old_or_failed_order(): void
    {
        $rider = $this->userAt('rider');
        $other = $this->userAt('rider');

        foreach ([
            $this->delivered($other),
            $this->delivered($rider, ['delivered_at' => now()->subHours(49)]),
            $this->delivered($rider, ['status' => 'failed']),
        ] as $order) {
            $this->asRider($rider)->post(route('rider.refunds.store', $order->id), ['reason' => 'x'])->assertForbidden();
        }

        $this->assertSame(0, Refund::withoutGlobalScopes()->count());
    }

    public function test_only_one_open_request_per_order(): void
    {
        $rider = $this->userAt('rider');
        $order = $this->delivered($rider);

        $this->asRider($rider)->post(route('rider.refunds.store', $order->id), ['reason' => 'Cold food']);
        $this->asRider($rider)->post(route('rider.refunds.store', $order->id), ['reason' => 'Again'])
            ->assertSessionHasErrors('refund_'.$order->id);

        $this->assertSame(1, Refund::withoutGlobalScopes()->count());
    }

    public function test_a_manager_approves_and_the_rider_hands_the_cash_back(): void
    {
        $rider = $this->userAt('rider');
        $manager = $this->userAt('manager');
        $order = $this->delivered($rider);
        $this->asRider($rider)->post(route('rider.refunds.store', $order->id), ['reason' => 'Missing items']);
        $refund = Refund::withoutGlobalScopes()->sole();

        $this->asRider($rider)->post(route('rider.refunds.complete', $refund->id))->assertSessionHasErrors('refund_'.$order->id); // not approved yet

        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('dashboard.refunds.approve', $refund))->assertSessionHasNoErrors();

        $this->asRider($rider)->get(route('rider.history'))->assertSee('Refund approved: give GH₵65.00 back to the customer.');
        $this->asRider($rider)->post(route('rider.refunds.complete', $refund->id))->assertSessionHasNoErrors();

        $this->assertSame('completed', $refund->fresh()->status);
        $this->assertSame($rider->id, $refund->fresh()->completed_by);
        $this->asRider($rider)->get(route('rider.history'))->assertSee('Refunded GH₵65.00.');
    }

    public function test_a_rider_cannot_complete_a_refund_on_an_order_they_never_held_cash_for(): void
    {
        $rider = $this->userAt('rider');
        $manager = $this->userAt('manager');
        $order = $this->delivered($rider, ['payment_method' => 'paystack']);
        $this->asRider($rider)->post(route('rider.refunds.store', $order->id), ['reason' => 'Wrong order']);
        $refund = Refund::withoutGlobalScopes()->sole();
        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branch->id])->post(route('dashboard.refunds.approve', $refund));

        $this->asRider($rider)->post(route('rider.refunds.complete', $refund->id))->assertForbidden();
        $this->asRider($rider)->get(route('rider.history'))->assertSee('The branch will refund the customer.');
    }

    public function test_a_full_refund_takes_the_delivery_fee_off_the_riders_totals(): void
    {
        $rider = $this->userAt('rider');
        $manager = $this->userAt('manager');
        $order = $this->delivered($rider);
        $this->asRider($rider)->post(route('rider.refunds.store', $order->id), ['reason' => 'Wrong order']);
        $refund = Refund::withoutGlobalScopes()->sole();
        $this->actingAs($manager)->withSession(['current_branch_id' => $this->branch->id])->post(route('dashboard.refunds.approve', $refund));
        $this->asRider($rider)->post(route('rider.refunds.complete', $refund->id));

        $this->assertSame(['fee' => 0, 'cash' => 0, 'prepaid' => 0], app(DeliveryFeeReportService::class)->split($order->fresh()));
    }

    public function test_past_deliveries_offer_the_request_only_where_allowed(): void
    {
        $rider = $this->userAt('rider');
        $this->delivered($rider);

        $this->asRider($rider)->get(route('rider.history'))->assertOk()->assertSee('Request refund')->assertSee('Full refund of GH₵65.00');

        $old = $this->userAt('rider');
        $this->delivered($old, ['delivered_at' => now()->subDays(3)]);
        $this->asRider($old)->get(route('rider.history'))->assertOk()->assertDontSee('Request refund');
    }
}
