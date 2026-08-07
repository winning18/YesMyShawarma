<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WeeklyReportTest extends TestCase
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

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    private function makeOrder(string $status, int $total, ?Carbon $placedAt = null, ?Customer $customer = null, string $paymentMethod = 'cash'): Order
    {
        $customer ??= Customer::create(['phone' => '+2332'.random_int(10000000, 99999999), 'name' => 'Ama Mensah']);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
            'channel' => 'web',
        ]);
        $order->status = $status;
        $order->placed_at = $placedAt ?? now('Africa/Accra');
        $order->save();

        return $order;
    }

    public function test_manager_can_view_the_weekly_report_page(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.reports.weekly.index'))
            ->assertOk()
            ->assertSee('Weekly report')
            ->assertSee('Download CSV');
    }

    public function test_staff_cannot_view_the_weekly_report_page(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.reports.weekly.index'))->assertForbidden();
    }

    public function test_download_lists_every_order_placed_that_week(): void
    {
        $manager = $this->makeManager();
        $monday = now('Africa/Accra')->startOfWeek();
        $customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama Mensah']);

        $this->makeOrder('delivered', 5000, $monday->clone()->addDay(), $customer);
        $this->makeOrder('delivered', 3000, $monday->clone()->subDays(3)); // previous week — excluded

        $response = $this->actingAs($manager)->get(route('dashboard.reports.weekly.download', [
            'week' => $monday->toDateString(),
        ]));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Ama Mensah', $content);
        $this->assertStringContainsString('+233241111111', $content);
        $this->assertStringContainsString('50.00', $content);
    }

    public function test_download_includes_the_payment_method_column(): void
    {
        $manager = $this->makeManager();
        $monday = now('Africa/Accra')->startOfWeek();

        $this->makeOrder('delivered', 5000, $monday->clone()->addDay(), paymentMethod: 'momo');

        $response = $this->actingAs($manager)->get(route('dashboard.reports.weekly.download', [
            'week' => $monday->toDateString(),
        ]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Payment method', $content);
        $this->assertStringContainsString('momo', $content);
    }

    public function test_download_excludes_orders_from_other_weeks(): void
    {
        $manager = $this->makeManager();
        $monday = now('Africa/Accra')->startOfWeek();

        $inWeek = $this->makeOrder('delivered', 5000, $monday->clone()->addDay());
        $outsideWeek = $this->makeOrder('delivered', 3000, $monday->clone()->subDays(3));

        $response = $this->actingAs($manager)->get(route('dashboard.reports.weekly.download', [
            'week' => $monday->toDateString(),
        ]));

        $content = $response->streamedContent();
        $this->assertStringContainsString($inWeek->reference, $content);
        $this->assertStringNotContainsString($outsideWeek->reference, $content);
    }
}
