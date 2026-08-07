<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PromotionManagementTest extends TestCase
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

    public function test_manager_can_view_the_promotions_index(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.promotions.index'))->assertOk();
    }

    public function test_staff_cannot_view_the_promotions_index(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.promotions.index'))->assertForbidden();
    }

    public function test_manager_can_create_a_percentage_promotion(): void
    {
        $manager = $this->makeManager();

        $response = $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'welcome10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => '1',
        ]);

        $promotion = Promotion::where('code', 'WELCOME10')->first();

        $response->assertRedirect(route('dashboard.promotions.index'));
        $this->assertSame(10, $promotion->value);
        $this->assertTrue($promotion->is_active);
    }

    public function test_manager_can_create_a_fixed_promotion_with_cedis_converted_to_pesewas(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'FIVEOFF',
            'type' => 'fixed',
            'value' => '5.00',
            'min_order_total' => '20.00',
        ]);

        $promotion = Promotion::where('code', 'FIVEOFF')->first();

        $this->assertSame(500, $promotion->value);
        $this->assertSame(2000, $promotion->min_order_total);
    }

    public function test_a_percentage_value_over_100_is_rejected(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'TOOMUCH',
            'type' => 'percentage',
            'value' => 150,
        ])->assertSessionHasErrors('value');
    }

    public function test_manager_can_restrict_a_promotion_to_specific_branches(): void
    {
        $manager = $this->makeManager();
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'OSUONLY',
            'type' => 'percentage',
            'value' => 10,
            'branch_ids' => [$this->branch->id],
        ]);

        $promotion = Promotion::where('code', 'OSUONLY')->first();

        $this->assertTrue($promotion->branches->contains('id', $this->branch->id));
        $this->assertFalse($promotion->branches->contains('id', $otherBranch->id));
    }

    public function test_manager_can_remove_a_promotion(): void
    {
        $manager = $this->makeManager();
        $promotion = Promotion::create(['code' => 'GONE', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($manager)->delete(route('dashboard.promotions.destroy', $promotion))
            ->assertRedirect(route('dashboard.promotions.index'));

        $this->assertSoftDeleted($promotion);
    }
}
