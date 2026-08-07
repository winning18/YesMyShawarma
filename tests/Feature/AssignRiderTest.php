<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AssignRiderTest extends TestCase
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

    private function onShiftRider(?string $name = null): User
    {
        $rider = User::factory()->create($name ? ['name' => $name] : []);
        $this->assignRoleAt($rider, 'rider', $this->branch);
        Shift::create(['user_id' => $rider->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);

        return $rider;
    }

    private function makeOrder(array $overrides = []): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create(array_merge([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ], $overrides));
        $order->status = $overrides['status'] ?? 'ready';
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_staff_can_manually_assign_a_rider(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $rider = $this->onShiftRider('Kwame');
        $order = $this->makeOrder();

        $this->actingAs($staff)
            ->postJson(route('orders.assign_rider', $order), ['rider_id' => $rider->id])
            ->assertOk()
            ->assertJsonPath('data.rider_id', $rider->id);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'actor_type' => 'staff',
            'actor_id' => $staff->id,
        ]);
    }

    public function test_manager_can_reassign_an_already_assigned_order(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);
        $originalRider = $this->onShiftRider('Kwame');
        $newRider = $this->onShiftRider('Yaw');

        $order = $this->makeOrder(['status' => 'dispatched']);
        $order->rider_id = $originalRider->id;
        $order->claimed_at = now();
        $order->save();

        $this->actingAs($manager)
            ->postJson(route('orders.assign_rider', $order), ['rider_id' => $newRider->id])
            ->assertOk()
            ->assertJsonPath('data.rider_id', $newRider->id);
    }

    public function test_rider_cannot_manually_assign(): void
    {
        $rider = $this->onShiftRider();
        $order = $this->makeOrder();

        $this->actingAs($rider)
            ->postJson(route('orders.assign_rider', $order), ['rider_id' => $rider->id])
            ->assertForbidden();
    }

    public function test_cannot_assign_a_pickup_order(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $rider = $this->onShiftRider();
        $order = $this->makeOrder(['fulfilment_type' => 'pickup']);

        $this->actingAs($staff)
            ->postJson(route('orders.assign_rider', $order), ['rider_id' => $rider->id])
            ->assertForbidden();
    }

    public function test_cannot_assign_a_rider_who_is_not_on_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $offShiftRider = User::factory()->create();
        $this->assignRoleAt($offShiftRider, 'rider', $this->branch);
        $order = $this->makeOrder();

        $this->actingAs($staff)
            ->postJson(route('orders.assign_rider', $order), ['rider_id' => $offShiftRider->id])
            ->assertUnprocessable();
    }

    public function test_cannot_assign_a_user_who_is_not_a_rider(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $otherStaff = User::factory()->create();
        $this->assignRoleAt($otherStaff, 'staff', $this->branch);
        Shift::create(['user_id' => $otherStaff->id, 'branch_id' => $this->branch->id, 'started_at' => now()]);
        $order = $this->makeOrder();

        $this->actingAs($staff)
            ->postJson(route('orders.assign_rider', $order), ['rider_id' => $otherStaff->id])
            ->assertUnprocessable();
    }

    public function test_on_shift_riders_endpoint_lists_only_current_branch(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);
        $onShift = $this->onShiftRider('Kwame');

        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $elsewhereRider = User::factory()->create();
        $this->assignRoleAt($elsewhereRider, 'rider', $otherBranch);
        Shift::create(['user_id' => $elsewhereRider->id, 'branch_id' => $otherBranch->id, 'started_at' => now()]);

        $offShiftRider = User::factory()->create();
        $this->assignRoleAt($offShiftRider, 'rider', $this->branch);

        $response = $this->actingAs($staff)->getJson(route('dashboard.riders'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($onShift->id));
        $this->assertFalse($ids->contains($elsewhereRider->id));
        $this->assertFalse($ids->contains($offShiftRider->id));
    }
}
