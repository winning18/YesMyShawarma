<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\User;
use App\Services\DamageReports\DamageReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DamageReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

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

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        return $staff;
    }

    private function makeManager(): User
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        return $manager;
    }

    private function makeRider(): User
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);
        $rider->update(['current_branch_id' => $this->branch->id]);

        return $rider;
    }

    private function makeOrder(?int $riderId = null): Order
    {
        $customer = Customer::create(['phone' => '+2332'.random_int(10000000, 99999999)]);

        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'delivery',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // status and rider_id aren't mass-assignable (Order's Fillable
        // list) — set via the state machine/RiderAssignmentService in real
        // code, direct attribute assignment here same as other tests.
        $order->status = 'dispatched';
        $order->rider_id = $riderId;
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_staff_can_file_a_general_damage_report_with_a_stock_item(): void
    {
        $staff = $this->makeStaff();
        $stockItem = StockItem::create([
            'branch_id' => $this->branch->id, 'name' => 'Pita Bread', 'unit' => 'pieces',
            'quantity' => 20, 'low_stock_threshold' => 5, 'created_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)->post(route('dashboard.damage-reports.store'), [
            'stock_item_id' => $stockItem->id,
            'description' => 'A box of bread got wet in the rain.',
            'photo' => UploadedFile::fake()->image('damage.jpg'),
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('damage_reports', [
            'branch_id' => $this->branch->id,
            'stock_item_id' => $stockItem->id,
            'order_id' => null,
            'reported_by' => $staff->id,
            'reporter_role' => 'staff',
            'status' => 'pending',
        ]);

        $report = DamageReport::first();
        Storage::disk('local')->assertExists($report->photo_path);
    }

    public function test_photo_is_required_to_file_a_report(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->post(route('dashboard.damage-reports.store'), [
            'description' => 'Something broke.',
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('damage_reports', 0);
    }

    public function test_a_rider_can_report_damage_on_their_own_assigned_order(): void
    {
        $rider = $this->makeRider();
        $order = $this->makeOrder($rider->id);

        $response = $this->actingAs($rider, 'rider')->post(route('dashboard.damage-reports.store'), [
            'order_id' => $order->id,
            'description' => 'The delivery bag tore and the drink spilled.',
            'photo' => UploadedFile::fake()->image('spill.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk();

        $this->assertDatabaseHas('damage_reports', [
            'order_id' => $order->id,
            'reported_by' => $rider->id,
            'reporter_role' => 'rider',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_a_rider_cannot_report_damage_on_an_order_not_assigned_to_them(): void
    {
        $rider = $this->makeRider();
        $otherRider = $this->makeRider();
        $order = $this->makeOrder($otherRider->id);

        $this->actingAs($rider, 'rider')->post(route('dashboard.damage-reports.store'), [
            'order_id' => $order->id,
            'description' => 'Trying to report someone else\'s order.',
            'photo' => UploadedFile::fake()->image('spill.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertDatabaseCount('damage_reports', 0);
    }

    public function test_manager_can_approve_a_pending_report_staff_cannot(): void
    {
        $staff = $this->makeStaff();
        $manager = $this->makeManager();

        $report = app(DamageReportService::class)->file(
            reporter: $staff, reporterRole: 'staff', branchId: $this->branch->id,
            description: 'Broke.', photo: UploadedFile::fake()->image('a.jpg'),
        );

        $this->actingAs($staff)->post(route('dashboard.damage-reports.approve', $report))->assertForbidden();

        $this->actingAs($manager)->post(route('dashboard.damage-reports.approve', $report))->assertRedirect();

        $this->assertDatabaseHas('damage_reports', [
            'id' => $report->id, 'status' => 'approved', 'reviewed_by' => $manager->id,
        ]);
    }

    public function test_manager_can_deny_a_pending_report(): void
    {
        $staff = $this->makeStaff();
        $manager = $this->makeManager();

        $report = app(DamageReportService::class)->file(
            reporter: $staff, reporterRole: 'staff', branchId: $this->branch->id,
            description: 'Broke.', photo: UploadedFile::fake()->image('a.jpg'),
        );

        $this->actingAs($manager)->post(route('dashboard.damage-reports.deny', $report), [
            'note' => 'Not consistent with the photo.',
        ])->assertRedirect();

        $this->assertDatabaseHas('damage_reports', [
            'id' => $report->id, 'status' => 'denied', 'review_note' => 'Not consistent with the photo.',
        ]);
    }

    public function test_an_already_reviewed_report_cannot_be_approved_again(): void
    {
        $staff = $this->makeStaff();
        $manager = $this->makeManager();

        $report = app(DamageReportService::class)->file(
            reporter: $staff, reporterRole: 'staff', branchId: $this->branch->id,
            description: 'Broke.', photo: UploadedFile::fake()->image('a.jpg'),
        );
        app(DamageReportService::class)->deny($report, $manager, null);

        $this->actingAs($manager)->post(route('dashboard.damage-reports.approve', $report))
            ->assertSessionHasErrors('damage_report');

        $this->assertDatabaseHas('damage_reports', ['id' => $report->id, 'status' => 'denied']);
    }

    public function test_viewing_the_photo_as_a_reviewer_marks_it_viewed_but_the_reporter_viewing_it_does_not(): void
    {
        $staff = $this->makeStaff();
        $manager = $this->makeManager();

        $report = app(DamageReportService::class)->file(
            reporter: $staff, reporterRole: 'staff', branchId: $this->branch->id,
            description: 'Broke.', photo: UploadedFile::fake()->image('a.jpg'),
        );

        $this->actingAs($staff)->get(route('dashboard.damage-reports.photo', $report))->assertOk();
        $this->assertNull($report->fresh()->photo_viewed_at);

        $this->actingAs($manager)->get(route('dashboard.damage-reports.photo', $report))->assertOk();
        $this->assertNotNull($report->fresh()->photo_viewed_at);
    }

    public function test_scheduled_command_deletes_photos_more_than_24h_after_being_viewed_but_keeps_the_row(): void
    {
        $staff = $this->makeStaff();

        $report = app(DamageReportService::class)->file(
            reporter: $staff, reporterRole: 'staff', branchId: $this->branch->id,
            description: 'Broke.', photo: UploadedFile::fake()->image('a.jpg'),
        );
        $photoPath = $report->photo_path;

        // Not yet viewed — untouched regardless of age.
        $report->update(['created_at' => now()->subDays(10)]);
        $this->artisan('damage-reports:delete-expired-photos');
        Storage::disk('local')->assertExists($photoPath);

        // Viewed just now — still within the 24h window.
        $report->update(['photo_viewed_at' => now()]);
        $this->artisan('damage-reports:delete-expired-photos');
        Storage::disk('local')->assertExists($photoPath);
        $this->assertNotNull($report->fresh()->photo_path);

        // Viewed more than 24h ago — now deleted, row stays.
        $report->update(['photo_viewed_at' => now()->subHours(25)]);
        $this->artisan('damage-reports:delete-expired-photos');
        Storage::disk('local')->assertMissing($photoPath);
        $this->assertDatabaseHas('damage_reports', ['id' => $report->id, 'photo_path' => null]);
    }
}
