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

class ShiftTest extends TestCase
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

    public function test_staff_can_start_and_end_a_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->getJson(route('shift.show'))->assertJsonPath('active', false);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->getJson(route('shift.show'))->assertJsonPath('active', true);

        $this->assertDatabaseHas('shifts', [
            'user_id' => $staff->id, 'branch_id' => $this->branch->id, 'ended_at' => null,
        ]);

        $this->actingAs($staff)->postJson(route('shift.end'))->assertOk();

        $this->actingAs($staff)->getJson(route('shift.show'))->assertJsonPath('active', false);
    }

    public function test_cannot_start_a_second_shift_while_one_is_active(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $this->actingAs($staff)->postJson(route('shift.start'))->assertUnprocessable();
    }

    public function test_ending_with_no_active_shift_is_rejected(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.end'))->assertUnprocessable();
    }

    public function test_order_actions_attribute_to_the_actors_active_shift(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->postJson(route('shift.start'))->assertOk();

        $customer = Customer::create(['phone' => '+233241111111']);
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 3500,
            'total' => 3500,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->status = 'paid';
        $order->save();

        $this->actingAs($staff)->postJson(route('orders.accept', $order))->assertOk();

        $shift = Shift::where('user_id', $staff->id)->first();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id, 'to_status' => 'accepted', 'shift_id' => $shift->id,
        ]);
    }
}
