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

class PaymentConfirmationTest extends TestCase
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

    private function pendingMomoOrder(): Order
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
            'payment_method' => 'momo',
            'payment_status' => 'pending',
            'channel' => 'pos',
        ]);
        $order->status = 'paid';
        $order->placed_at = now();
        $order->save();

        $order->payments()->create([
            'provider' => 'momo', 'amount' => 3500, 'currency' => 'GHS', 'status' => 'pending',
        ]);

        return $order;
    }

    public function test_staff_can_confirm_a_skipped_momo_transaction_id(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $order = $this->pendingMomoOrder();

        $this->actingAs($staff)
            ->postJson(route('orders.confirm_momo_payment', $order), ['transaction_id' => 'MOMO-TXN-999'])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'momo',
            'provider_reference' => 'MOMO-TXN-999', 'status' => 'paid',
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'from_status' => 'paid', 'to_status' => 'paid',
        ]);

        // status (the business/kitchen-flow field) never moves — only
        // payment_status, the reconciliation bookkeeping, changes.
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_reused_transaction_id_is_rejected(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $alreadyUsed = $this->pendingMomoOrder();
        $alreadyUsed->payments()->first()->update(['provider_reference' => 'MOMO-TXN-DUPLICATE', 'status' => 'paid']);

        $order = $this->pendingMomoOrder();

        $this->actingAs($staff)
            ->postJson(route('orders.confirm_momo_payment', $order), ['transaction_id' => 'MOMO-TXN-DUPLICATE'])
            ->assertUnprocessable();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_cannot_confirm_momo_payment_for_a_cash_order(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $order = $this->pendingMomoOrder();
        $order->update(['payment_method' => 'cash']);

        $this->actingAs($staff)
            ->postJson(route('orders.confirm_momo_payment', $order), ['transaction_id' => 'MOMO-TXN-1'])
            ->assertUnprocessable();
    }

    public function test_rider_cannot_confirm_momo_payment(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        $order = $this->pendingMomoOrder();

        $this->actingAs($rider)
            ->postJson(route('orders.confirm_momo_payment', $order), ['transaction_id' => 'MOMO-TXN-1'])
            ->assertForbidden();
    }
}
