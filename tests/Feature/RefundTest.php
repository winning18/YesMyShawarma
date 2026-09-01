<?php

namespace Tests\Feature;

use App\Contracts\Notifier;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\Orders\RefundService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RefundTest extends TestCase
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

    private function makeOwner(): User
    {
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        return $owner;
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        return $staff;
    }

    private function makeManager(?Branch $branch = null): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $branch ?? $this->branch);

        return $manager;
    }

    private function makeGeneralManager(?Branch $branch = null): User
    {
        $gm = User::factory()->create();
        $this->assignRoleAt($gm, 'general_manager', $branch ?? $this->branch);

        return $gm;
    }

    private function makePaidOrder(int $total = 5000, string $paymentMethod = 'cash', string $channel = 'web', ?Branch $branch = null): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => ($branch ?? $this->branch)->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
            'channel' => $channel,
        ]);
        $order->status = 'delivered';
        $order->placed_at = now();
        $order->save();

        if ($paymentMethod === 'paystack') {
            $order->payments()->create([
                'provider' => 'paystack', 'provider_reference' => 'PSK-'.uniqid(),
                'amount' => $total, 'currency' => 'GHS', 'status' => 'paid', 'verified_at' => now(),
            ]);
        }

        return $order;
    }

    public function test_staff_can_request_a_refund(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makePaidOrder(5000);

        $response = $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Customer received the wrong item.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id, 'amount' => 2000, 'status' => 'pending', 'requested_by' => $staff->id,
        ]);
    }

    public function test_rider_cannot_request_a_refund(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);
        $order = $this->makePaidOrder(5000);

        $this->actingAs($rider)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Test',
        ])->assertForbidden();
    }

    public function test_cannot_request_more_than_the_order_total(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makePaidOrder(5000);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '60.00', 'reason' => 'Test',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_cannot_refund_an_unpaid_order(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makePaidOrder(5000);
        $order->update(['payment_status' => 'pending']);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Test',
        ])->assertSessionHasErrors('amount');
    }

    public function test_owner_direct_refund_completes_immediately_for_a_cash_order(): void
    {
        $owner = $this->makeOwner();
        $order = $this->makePaidOrder(5000, 'cash');

        $response = $this->actingAs($owner)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Goodwill refund.',
        ]);

        $response->assertRedirect();
        $refund = Refund::first();
        $this->assertSame('completed', $refund->status);
        $this->assertSame($owner->id, $refund->requested_by);
        $this->assertSame($owner->id, $refund->reviewed_by);
        $this->assertSame($owner->id, $refund->completed_by);
        $this->assertNotNull($refund->completed_at);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'cash', 'amount' => 2000, 'status' => 'refunded',
        ]);
    }

    public function test_completing_a_refund_sms_notifies_the_customer(): void
    {
        $owner = $this->makeOwner();
        $order = $this->makePaidOrder(5000, 'cash');

        $this->mock(Notifier::class, function ($mock) use ($order) {
            $mock->shouldReceive('notify')->once()
                ->with($order->customer->phone, \Mockery::type('string'), ['order_id' => $order->id, 'refund_id' => 1]);
        });

        $this->actingAs($owner)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Goodwill refund.',
        ]);
    }

    public function test_owner_direct_refund_calls_paystack_for_a_paystack_order(): void
    {
        Http::fake([
            'api.paystack.co/refund' => Http::response([
                'status' => true,
                'data' => ['id' => 999, 'transaction' => ['reference' => 'PSK-original']],
            ], 200),
        ]);

        $owner = $this->makeOwner();
        $order = $this->makePaidOrder(5000, 'paystack');
        $originalReference = $order->payments()->first()->provider_reference;

        $this->actingAs($owner)->post(route('orders.refunds.store', $order), [
            'amount' => '50.00', 'reason' => 'Full refund.',
        ])->assertRedirect();

        Http::assertSent(function ($request) use ($originalReference) {
            return $request->url() === 'https://api.paystack.co/refund'
                && $request['transaction'] === $originalReference
                && $request['amount'] === 5000;
        });

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'status' => 'refunded', 'provider_reference' => '999',
        ]);
    }

    public function test_manager_direct_refund_completes_immediately(): void
    {
        $manager = $this->makeManager();
        $order = $this->makePaidOrder(5000, 'cash');

        $response = $this->actingAs($manager)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Goodwill refund.',
        ]);

        $response->assertRedirect();
        $refund = Refund::first();
        $this->assertSame('completed', $refund->status);
        $this->assertSame($manager->id, $refund->requested_by);
        $this->assertSame($manager->id, $refund->reviewed_by);
        $this->assertSame($manager->id, $refund->completed_by);
    }

    public function test_general_manager_direct_refund_completes_immediately(): void
    {
        $gm = $this->makeGeneralManager();
        $order = $this->makePaidOrder(5000, 'cash');

        $this->actingAs($gm)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Goodwill refund.',
        ])->assertRedirect();

        $this->assertSame('completed', Refund::first()->status);
    }

    public function test_manager_can_approve_and_deny_a_staff_request(): void
    {
        $staff = $this->makeStaff();
        $manager = $this->makeManager();
        $order = $this->makePaidOrder(5000);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);
        $refund = Refund::first();

        $this->actingAs($manager)->post(route('dashboard.refunds.approve', $refund))->assertRedirect();
        $this->assertSame('approved', $refund->fresh()->status);
    }

    public function test_general_manager_can_approve_a_staff_request(): void
    {
        $staff = $this->makeStaff();
        $gm = $this->makeGeneralManager();
        $order = $this->makePaidOrder(5000);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);
        $refund = Refund::first();

        $this->actingAs($gm)->post(route('dashboard.refunds.approve', $refund))->assertRedirect();
        $this->assertSame('approved', $refund->fresh()->status);
    }

    public function test_manager_cannot_act_on_a_refund_at_a_branch_they_dont_hold(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $staff = $this->makeStaff();
        $manager = $this->makeManager($otherBranch);
        $order = $this->makePaidOrder(5000);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);
        $refund = Refund::first();

        $this->actingAs($manager)->post(route('dashboard.refunds.approve', $refund))->assertForbidden();
    }

    public function test_approve_then_complete_workflow(): void
    {
        $staff = $this->makeStaff();
        $owner = $this->makeOwner();
        $order = $this->makePaidOrder(5000);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);
        $refund = Refund::first();

        // Staff cannot complete a refund that isn't approved yet.
        $this->actingAs($staff)->post(route('dashboard.refunds.complete', $refund))
            ->assertSessionHasErrors('refund');
        $this->assertSame('pending', $refund->fresh()->status);

        $this->actingAs($owner)->post(route('dashboard.refunds.approve', $refund), [
            'note' => 'Approved, go ahead.',
        ])->assertRedirect();
        $this->assertSame('approved', $refund->fresh()->status);

        $this->actingAs($staff)->post(route('dashboard.refunds.complete', $refund))->assertRedirect();

        $refund->refresh();
        $this->assertSame('completed', $refund->status);
        $this->assertSame($staff->id, $refund->completed_by);
    }

    public function test_owner_can_deny_a_pending_request(): void
    {
        $staff = $this->makeStaff();
        $owner = $this->makeOwner();
        $order = $this->makePaidOrder(5000);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);
        $refund = Refund::first();

        $this->actingAs($owner)->post(route('dashboard.refunds.deny', $refund), [
            'note' => 'Item was correct per the photo.',
        ])->assertRedirect();

        $this->assertSame('denied', $refund->fresh()->status);
    }

    public function test_staff_cannot_approve_or_deny_a_refund(): void
    {
        $staff = $this->makeStaff();
        $order = $this->makePaidOrder(5000);

        $this->actingAs($staff)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);
        $refund = Refund::first();

        $this->actingAs($staff)->post(route('dashboard.refunds.approve', $refund))->assertForbidden();
        $this->actingAs($staff)->post(route('dashboard.refunds.deny', $refund))->assertForbidden();
    }

    public function test_multiple_completed_refunds_cannot_exceed_the_order_total(): void
    {
        $owner = $this->makeOwner();
        $order = $this->makePaidOrder(5000);

        $this->actingAs($owner)->post(route('orders.refunds.store', $order), [
            'amount' => '30.00', 'reason' => 'First refund.',
        ])->assertRedirect();

        // 5000 - 3000 = 2000 pesewas (GH₵20) left refundable.
        $remaining = app(RefundService::class)->remainingBalance($order->fresh());
        $this->assertSame(2000, $remaining);

        $this->actingAs($owner)->post(route('orders.refunds.store', $order), [
            'amount' => '30.00', 'reason' => 'Second refund, too much.',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_staff_can_view_the_refunds_index(): void
    {
        $owner = $this->makeOwner();
        $staff = $this->makeStaff();

        $this->actingAs($owner)->get(route('dashboard.refunds.index'))->assertOk();
        $this->actingAs($staff)->get(route('dashboard.refunds.index'))->assertOk();
    }

    public function test_rider_cannot_view_the_refunds_index(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        $this->actingAs($rider)->get(route('dashboard.refunds.index'))->assertForbidden();
    }

    public function test_refunds_index_never_shows_staff_the_approve_or_deny_buttons(): void
    {
        $requester = $this->makeStaff();
        $order = $this->makePaidOrder(5000);
        $this->actingAs($requester)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);

        // A second staff account, viewing the same pending request.
        $viewer = $this->makeStaff();
        $response = $this->actingAs($viewer)->get(route('dashboard.refunds.index'));

        $response->assertDontSee('action="'.route('dashboard.refunds.approve', Refund::first()).'"', false);
        $response->assertDontSee('action="'.route('dashboard.refunds.deny', Refund::first()).'"', false);
        // Not a plain assertDontSee(__('Approve')) — the status filter's
        // "Approved" option contains "Approve" as a substring.
        $response->assertDontSee('>'.__('Approve').'<', false);
        $response->assertDontSee('>'.__('Deny').'<', false);
    }

    public function test_refunds_index_shows_staff_the_complete_button_once_approved(): void
    {
        $requester = $this->makeStaff();
        $owner = $this->makeOwner();
        $order = $this->makePaidOrder(5000);
        $this->actingAs($requester)->post(route('orders.refunds.store', $order), [
            'amount' => '20.00', 'reason' => 'Wrong item.',
        ]);
        $refund = Refund::first();
        $this->actingAs($owner)->post(route('dashboard.refunds.approve', $refund));

        $response = $this->actingAs($requester)->get(route('dashboard.refunds.index'));

        $response->assertSee('action="'.route('dashboard.refunds.complete', $refund).'"', false);
        $response->assertSee('>'.__('Complete').'<', false);
    }

    public function test_staff_refunds_index_is_scoped_to_their_own_branch(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $owner = $this->makeOwner();
        $ownOrder = $this->makePaidOrder(5000, branch: $this->branch);
        $this->actingAs($owner)->post(route('orders.refunds.store', $ownOrder), ['amount' => '10.00', 'reason' => 'a']);

        $otherOwner = User::factory()->create();
        $this->assignRoleAt($otherOwner, 'owner', $otherBranch);
        $otherOrder = $this->makePaidOrder(3000, branch: $otherBranch);
        $this->actingAs($otherOwner)->post(route('orders.refunds.store', $otherOrder), ['amount' => '10.00', 'reason' => 'b']);

        $staff = $this->makeStaff();
        $refunds = $this->actingAs($staff)->get(route('dashboard.refunds.index'))->viewData('refunds');

        $this->assertCount(1, $refunds);
        $this->assertSame($ownOrder->id, $refunds->first()->order_id);
    }

    public function test_refunds_index_is_reachable_by_manager_and_general_manager(): void
    {
        $manager = $this->makeManager();
        $gm = $this->makeGeneralManager();

        $this->actingAs($manager)->get(route('dashboard.refunds.index'))->assertOk();
        $this->actingAs($gm)->get(route('dashboard.refunds.index'))->assertOk();
    }

    public function test_refunds_index_scopes_by_branch_for_manager_and_general_manager(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $owner = $this->makeOwner();
        $ownOrder = $this->makePaidOrder(5000, branch: $this->branch);
        $this->actingAs($owner)->post(route('orders.refunds.store', $ownOrder), ['amount' => '10.00', 'reason' => 'a']);

        $otherOwner = User::factory()->create();
        $this->assignRoleAt($otherOwner, 'owner', $otherBranch);
        $otherOrder = $this->makePaidOrder(3000, branch: $otherBranch);
        $this->actingAs($otherOwner)->post(route('orders.refunds.store', $otherOrder), ['amount' => '10.00', 'reason' => 'b']);

        // Manager, pinned to $this->branch only, never sees the other branch's refund.
        $manager = $this->makeManager();
        $managerResponse = $this->actingAs($manager)->get(route('dashboard.refunds.index'));
        $managerRefunds = $managerResponse->viewData('refunds');
        $this->assertCount(1, $managerRefunds);
        $this->assertSame($ownOrder->id, $managerRefunds->first()->order_id);

        // A fresh owner (still only holding a role at $this->branch) sees both branches.
        $ownerResponse = $this->actingAs($this->makeOwner())->get(route('dashboard.refunds.index'));
        $ownerRefunds = $ownerResponse->viewData('refunds');
        $this->assertCount(2, $ownerRefunds);
    }

    public function test_refunds_index_filters_by_channel(): void
    {
        $owner = $this->makeOwner();
        $webOrder = $this->makePaidOrder(5000, 'cash', 'web');
        $posOrder = $this->makePaidOrder(3000, 'cash', 'pos');

        $this->actingAs($owner)->post(route('orders.refunds.store', $webOrder), ['amount' => '10.00', 'reason' => 'a']);
        $this->actingAs($owner)->post(route('orders.refunds.store', $posOrder), ['amount' => '10.00', 'reason' => 'b']);

        $response = $this->actingAs($owner)->get(route('dashboard.refunds.index', ['channel' => 'pos']));
        $refunds = $response->viewData('refunds');

        $this->assertCount(1, $refunds);
        $this->assertSame($posOrder->id, $refunds->first()->order_id);
    }

    public function test_refund_nav_link_shows_for_owner_manager_and_staff_not_rider(): void
    {
        $owner = $this->makeOwner();
        $manager = $this->makeManager();
        $staff = $this->makeStaff();
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        $this->actingAs($owner)->get(route('dashboard.refunds.index'))
            ->assertSee('href="'.route('dashboard.refunds.index').'"', false);

        $this->actingAs($manager)->get(route('dashboard.refunds.index'))
            ->assertSee('href="'.route('dashboard.refunds.index').'"', false);

        $this->actingAs($staff)->get(route('dashboard.refunds.index'))
            ->assertSee('href="'.route('dashboard.refunds.index').'"', false);

        $this->actingAs($rider)->get(route('rider.dashboard'))
            ->assertDontSee('href="'.route('dashboard.refunds.index').'"', false);
    }
}
