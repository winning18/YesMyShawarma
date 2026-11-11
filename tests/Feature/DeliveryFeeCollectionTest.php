<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryFeeCollection;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\DeliveryFeeReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeliveryFeeCollectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $other;

    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        // Hand-ins start 2026-11-11 (the migration's setting); this is the Saturday after.
        DB::table('settings')->updateOrInsert(['key' => 'delivery_fee_hand_in_starts_on'], ['value' => '2026-11-11']);
        Carbon::setTestNow(Carbon::parse('2026-11-14 15:00', 'Africa/Accra'));

        $this->branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->other = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->rider = $this->userWithRole('rider', 'Kofi');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithRole(string $role, ?string $name = null, ?Branch $branch = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        app(PermissionRegistrar::class)->setPermissionsTeamId(($branch ?? $this->branch)->id);
        $user->assignRole($role);

        return $user;
    }

    private function delivery(int $fee, string $deliveredAt, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)])->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 5000,
            'delivery_fee' => $fee,
            'total' => 5000 + $fee,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ], $overrides));
        $order->status = 'delivered';
        $order->rider_id = $this->rider->id;
        $order->delivered_at = Carbon::parse($deliveredAt, 'Africa/Accra');
        $order->save();

        return $order;
    }

    private function as(User $user, ?Branch $branch = null): static
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => ($branch ?? $this->branch)->id]);
    }

    private function collect(User $by, int $receivedCedis, array $overrides = [])
    {
        return $this->as($by)->post(route('dashboard.reports.delivery-fees.collections.store'), [
            'branch' => $this->branch->id, 'rider' => $this->rider->id,
            'from' => '2026-11-09', 'to' => '2026-11-15', 'received' => $receivedCedis, ...$overrides,
        ]);
    }

    private function statuses(): array
    {
        return app(DeliveryFeeReportService::class)
            ->rows(Carbon::parse('2026-11-01', 'Africa/Accra'), Carbon::parse('2026-11-30', 'Africa/Accra')->endOfDay(), ignoreBranchScope: true)
            ->mapWithKeys(fn (array $row) => [$row['reference'] => $row['collection_status']])
            ->all();
    }

    public function test_each_delivery_shows_whether_its_fee_is_owed(): void
    {
        $this->delivery(1500, '2026-11-10 12:00', ['reference' => 'BEFORE']);
        $this->delivery(2000, '2026-11-12 12:00', ['reference' => 'CASH']);
        $this->delivery(1800, '2026-11-12 13:00', ['reference' => 'MOMO', 'payment_method' => 'momo', 'delivery_fee_paid_by' => 'momo']);

        $statuses = $this->statuses();
        ksort($statuses);
        $this->assertSame(['BEFORE' => 'kept', 'CASH' => 'to_collect', 'MOMO' => 'prepaid'], $statuses);
    }

    public function test_a_manager_records_a_collection_with_a_shortfall(): void
    {
        $a = $this->delivery(2000, '2026-11-12 12:00');
        $b = $this->delivery(1500, '2026-11-13 12:00');
        $this->delivery(1000, '2026-11-10 12:00'); // before hand-ins: the rider's
        $this->delivery(900, '2026-11-12 12:00', ['branch_id' => $this->other->id]); // another branch
        $manager = $this->userWithRole('manager', 'Esi');

        $this->as($manager)->get(route('dashboard.reports.delivery-fees.collections.index', ['rider' => $this->rider->id, 'from' => '2026-11-09', 'to' => '2026-11-15']))
            ->assertOk()
            ->assertSee('GH₵35.00')
            ->assertViewHas('owed', fn ($owed) => $owed['count'] === 2);

        $this->collect($manager, 30)->assertSessionHas('status', 'Collection recorded: 2 deliveries, GH₵30.00 received. Short by GH₵5.00.');

        $collection = DeliveryFeeCollection::withoutGlobalScopes()->sole();
        $this->assertSame([3500, 3000, -500], [$collection->expected, $collection->received, $collection->difference()]);
        $this->assertSame($manager->id, $collection->collected_by);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $collection->orders()->pluck('order_id')->all());
        $this->assertSame(['collected', 'collected'], array_values(array_intersect_key($this->statuses(), [$a->reference => 1, $b->reference => 1])));
    }

    public function test_a_delivery_is_never_collected_twice(): void
    {
        $this->delivery(2000, '2026-11-12 12:00');
        $manager = $this->userWithRole('manager');

        $this->collect($manager, 20)->assertSessionHas('status');
        $this->collect($manager, 20, ['from' => '2026-11-12', 'to' => '2026-11-12'])->assertSessionHasErrors('collection');

        $this->assertSame(1, DeliveryFeeCollection::withoutGlobalScopes()->count());
    }

    public function test_voiding_frees_the_deliveries_and_keeps_the_record(): void
    {
        $order = $this->delivery(2000, '2026-11-12 12:00');
        $manager = $this->userWithRole('manager');
        $this->collect($manager, 20);
        $collection = DeliveryFeeCollection::withoutGlobalScopes()->sole();

        $this->as($manager)->post(route('dashboard.reports.delivery-fees.collections.void', $collection), [])->assertSessionHasErrors('reason');
        $this->as($manager)->post(route('dashboard.reports.delivery-fees.collections.void', $collection), ['reason' => 'Wrong rider'])->assertSessionHas('status');

        $this->assertNotNull($collection->fresh()->voided_at);
        $this->assertSame('to_collect', $this->statuses()[$order->reference]);

        $this->collect($manager, 20)->assertSessionHas('status');
        $this->assertSame(2, DeliveryFeeCollection::withoutGlobalScopes()->count());
    }

    public function test_only_managers_and_up_at_that_branch_can_collect(): void
    {
        $this->delivery(2000, '2026-11-12 12:00');

        $this->collect($this->userWithRole('staff'), 20)->assertForbidden();
        $this->collect($this->userWithRole('manager', branch: $this->other), 20)->assertForbidden();

        $gm = $this->userWithRole('general_manager');
        $this->collect($gm, 20)->assertSessionHas('status');
    }

    public function test_the_report_filters_by_collection_and_shows_the_totals(): void
    {
        $this->delivery(2000, '2026-11-12 12:00', ['reference' => 'PAID-IN']);
        $this->delivery(1500, '2026-11-13 12:00', ['reference' => 'OWED']);
        $manager = $this->userWithRole('manager');
        $this->collect($manager, 20, ['from' => '2026-11-12', 'to' => '2026-11-12']);

        $response = $this->as($manager)->get(route('dashboard.reports.delivery-fees.index', ['from' => '2026-11-09', 'to' => '2026-11-15', 'collection' => 'to_collect']))->assertOk();

        $this->assertSame(['OWED'], collect($response->viewData('orders')->items())->pluck('reference')->all());
        $this->assertSame(1500, $response->viewData('summary')['to_collect']);
        $this->assertSame(2000, $response->viewData('summary')['collected']);
        $response->assertSee('Record a collection');

        $csv = $this->as($manager)->get(route('dashboard.reports.delivery-fees.download', ['from' => '2026-11-09', 'to' => '2026-11-15']))->streamedContent();
        $this->assertStringContainsString('Collected', $csv);
        $this->assertStringContainsString('To collect', $csv);
    }

    public function test_the_rider_sees_what_is_left_to_hand_in(): void
    {
        $this->delivery(2000, '2026-11-12 12:00');
        $this->delivery(1500, '2026-11-14 12:00');
        $this->delivery(1000, '2026-11-10 12:00'); // kept, never owed

        $this->actingAs($this->rider, 'rider')->withSession(['current_branch_id' => $this->branch->id])
            ->getJson(route('rider.orders.data'))
            ->assertOk()
            ->assertJsonPath('meta.delivery_fee_to_hand_in', 3500)
            ->assertJsonPath('meta.delivery_fee_cash_today', 1500);

        $this->actingAs($this->rider, 'rider')->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('rider.history'))
            ->assertOk()
            ->assertSee('to hand in')
            ->assertDontSee('from staff');
    }
}
