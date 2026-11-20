<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\RiderCashup;
use App\Models\Shift;
use App\Models\User;
use App\Services\Delivery\RiderCashupService;
use App\Services\Reports\DeliveryFeeReportService;
use App\Services\Reports\OrderReportService;
use App\Services\Reports\ProfitReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RiderCashupTest extends TestCase
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
        // The fee tests below: food money joins cash-ups later (the food tests set their own start).
        DB::table('settings')->updateOrInsert(['key' => 'rider_cashup_food_starts_on'], ['value' => '2027-01-01']);

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
        return $this->as($by)->post(route('dashboard.rider-cashups.store'), [
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

        $this->as($manager)->get(route('dashboard.rider-cashups.index', ['rider' => $this->rider->id, 'from' => '2026-11-09', 'to' => '2026-11-15']))
            ->assertOk()
            ->assertSee('GH₵35.00')
            ->assertViewHas('owed', fn ($owed) => $owed['count'] === 2);

        $this->collect($manager, 30)->assertSessionHas('status', 'Cash-up recorded: 2 deliveries, GH₵30.00 received. Short by GH₵5.00. No till is open on this device, so it isn\'t counted in any till.');

        $collection = RiderCashup::withoutGlobalScopes()->sole();
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

        $this->assertSame(1, RiderCashup::withoutGlobalScopes()->count());
    }

    public function test_voiding_frees_the_deliveries_and_keeps_the_record(): void
    {
        $order = $this->delivery(2000, '2026-11-12 12:00');
        $manager = $this->userWithRole('manager');
        $this->collect($manager, 20);
        $collection = RiderCashup::withoutGlobalScopes()->sole();

        $this->as($manager)->post(route('dashboard.rider-cashups.void', $collection), [])->assertSessionHasErrors('reason');
        $this->as($manager)->post(route('dashboard.rider-cashups.void', $collection), ['reason' => 'Wrong rider'])->assertSessionHas('status');

        $this->assertNotNull($collection->fresh()->voided_at);
        $this->assertSame('to_collect', $this->statuses()[$order->reference]);

        $this->collect($manager, 20)->assertSessionHas('status');
        $this->assertSame(2, RiderCashup::withoutGlobalScopes()->count());
    }

    public function test_cashiers_managers_and_up_at_that_branch_can_take_a_cash_up(): void
    {
        $this->delivery(2000, '2026-11-12 12:00');

        $this->collect($this->userWithRole('rider', branch: $this->branch), 20)->assertForbidden();
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
        $response->assertSee('Rider cash-up');

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
            ->assertJsonPath('meta.delivery_fee_cash_today', 6500); // today's GH₵50 food and GH₵15 fee, taken at the door

        $this->actingAs($this->rider, 'rider')->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('rider.history'))
            ->assertOk()
            ->assertSee('to hand in')
            ->assertDontSee('from staff');
    }

    /** A cash delivery taken at this till and delivered today, food GH₵50 + fee. */
    private function tillDelivery(Shift $till, int $fee, array $overrides = []): Order
    {
        $order = $this->delivery($fee, '2026-11-14 13:00', $overrides);
        $order->placed_at = Carbon::parse('2026-11-14 12:00', 'Africa/Accra');
        $order->shift_id = $till->id;
        $order->save();

        return $order;
    }

    private function openTill(User $by): Shift
    {
        return Shift::create(['user_id' => $by->id, 'branch_id' => $this->branch->id, 'started_at' => Carbon::parse('2026-11-14 08:00', 'Africa/Accra')]);
    }

    public function test_riders_hand_in_food_and_fees_at_a_till_and_it_counts_there(): void
    {
        DB::table('settings')->where('key', 'rider_cashup_food_starts_on')->update(['value' => '2026-11-14']);
        $cashier = $this->userWithRole('staff', 'Ama');
        $till = $this->openTill($cashier);
        $this->tillDelivery($till, 1500);
        $reports = app(OrderReportService::class);

        // The rider has the GH₵65 (food and fee), not the till.
        $this->assertSame(0, $reports->sharedTillSales($till));

        $owed = app(RiderCashupService::class)->owed($this->rider, $this->branch->id, Carbon::parse('2026-11-14', 'Africa/Accra'), Carbon::parse('2026-11-14', 'Africa/Accra')->endOfDay());
        $this->assertSame([5000, 1500, 0, 6500], [$owed['food'], $owed['fee'], $owed['fee_credit'], $owed['expected']]);

        $this->collect($cashier, 65, ['from' => '2026-11-14', 'to' => '2026-11-14'])->assertSessionHas('status');

        $cashup = RiderCashup::withoutGlobalScopes()->sole();
        $this->assertSame($till->id, $cashup->shift_id, 'received at the cashier\'s open till');
        $this->assertSame([5000, 1500, 6500], [$cashup->expected_food, $cashup->expected_fee, $cashup->expected]);
        $this->assertSame(6500, $reports->sharedTillSales($till), 'the till now expects what the rider handed in');
    }

    public function test_when_riders_keep_fees_they_hand_in_food_and_are_owed_fees_the_business_holds(): void
    {
        DB::table('settings')->where('key', 'rider_cashup_food_starts_on')->update(['value' => '2026-11-11']);
        DB::table('settings')->updateOrInsert(['key' => 'delivery_fee_handling'], ['value' => 'kept_by_rider']);
        $this->delivery(1500, '2026-11-12 12:00'); // cash: GH₵50 food handed in, GH₵15 fee kept
        $this->delivery(2000, '2026-11-13 12:00', ['payment_method' => 'momo', 'delivery_fee_paid_by' => 'momo']); // the business holds the GH₵20 fee

        $owed = app(RiderCashupService::class)->owed($this->rider, $this->branch->id, Carbon::parse('2026-11-09', 'Africa/Accra'), Carbon::parse('2026-11-15', 'Africa/Accra')->endOfDay());
        $this->assertSame([5000, 0, 2000, 3000], [$owed['food'], $owed['fee'], $owed['fee_credit'], $owed['expected']]);

        // Fees kept by riders are in no financial figure.
        $summary = app(DeliveryFeeReportService::class)->summary(app(DeliveryFeeReportService::class)->rows(Carbon::parse('2026-11-09', 'Africa/Accra'), Carbon::parse('2026-11-15', 'Africa/Accra')->endOfDay(), ignoreBranchScope: true));
        $this->assertSame(0, $summary['fee_income']);

        $this->collect($this->userWithRole('manager'), 30)->assertSessionHas('status');
        $this->assertSame('kept_by_rider', RiderCashup::withoutGlobalScopes()->sole()->fee_handling);
    }

    public function test_the_business_pays_the_rider_when_it_holds_more_of_their_fees_than_they_collected(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'delivery_fee_handling'], ['value' => 'kept_by_rider']);
        $this->delivery(2000, '2026-11-13 12:00', ['payment_method' => 'momo', 'delivery_fee_paid_by' => 'momo']);
        $manager = $this->userWithRole('manager');

        $this->as($manager)->get(route('dashboard.rider-cashups.index', ['rider' => $this->rider->id, 'from' => '2026-11-09', 'to' => '2026-11-15']))
            ->assertOk()->assertSee('The business owes Kofi')->assertSee('Amount paid to the rider');

        $this->collect($manager, 20, ['direction' => 'out'])->assertSessionHas('status', 'Cash-up recorded: 1 deliveries, GH₵20.00 paid to the rider. No till is open on this device, so it isn\'t counted in any till.');
        $this->assertSame([-2000, -2000, 0], [RiderCashup::withoutGlobalScopes()->sole()->expected, RiderCashup::withoutGlobalScopes()->sole()->received, RiderCashup::withoutGlobalScopes()->sole()->difference()]);
    }

    public function test_handed_in_fees_count_as_income_in_profit_and_only_the_owner_chooses(): void
    {
        $this->delivery(1500, '2026-11-12 12:00');
        $profit = fn () => app(ProfitReportService::class)->build(Carbon::parse('2026-11-01', 'Africa/Accra'), Carbon::parse('2026-11-30', 'Africa/Accra')->endOfDay(), 'month', ignoreBranchScope: true);

        $this->assertTrue($profit()['has_delivery_fees']);
        $this->assertSame(1500, $profit()['totals']['delivery_fees']);

        $manager = $this->userWithRole('manager');
        $this->as($manager)->get(route('dashboard.settings.index'))->assertOk()->assertDontSee('Who gets the delivery fee?');

        $owner = $this->userWithRole('owner');
        $this->as($owner)->get(route('dashboard.settings.index'))->assertOk()->assertSee('Who gets the delivery fee?');
        $page = $this->as($owner)->get(route('dashboard.settings.index'));
        $this->as($owner)->put(route('dashboard.settings.update'), [
            'order_reference_prefix_pos' => $page->viewData('posPrefix'),
            'order_reference_prefix_web' => $page->viewData('webPrefix'),
            'order_reference_prefix_bolt_food' => $page->viewData('boltFoodPrefix'),
            'order_reference_prefix_whatsapp' => $page->viewData('whatsappPrefix'),
            'order_reference_prefix_freebie' => $page->viewData('freebiePrefix'),
            'whatsapp_reward' => $page->viewData('whatsappReward'),
            ...$page->viewData('deliveryFees'),
            'delivery_fee_handling' => 'kept_by_rider',
        ])->assertSessionHasNoErrors();

        $this->assertSame('kept_by_rider', DB::table('settings')->where('key', 'delivery_fee_handling')->value('value'));
        $this->assertFalse($profit()['has_delivery_fees']);
        $this->assertSame(0, $profit()['totals']['delivery_fees']);
    }
}
