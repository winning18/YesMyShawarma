<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OptionGroupManagementTest extends TestCase
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

    public function test_manager_can_view_option_groups(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->get(route('dashboard.option-groups.index'))->assertOk();
    }

    public function test_staff_cannot_view_option_groups(): void
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        $this->actingAs($staff)->get(route('dashboard.option-groups.index'))->assertForbidden();
    }

    public function test_manager_can_create_an_option_group(): void
    {
        $manager = $this->makeManager();

        $response = $this->actingAs($manager)->post(route('dashboard.option-groups.store'), [
            'name' => 'Extras',
            'min_select' => 0,
            'max_select' => 3,
            'is_required' => '0',
        ]);

        $group = OptionGroup::where('name', 'Extras')->first();

        $response->assertRedirect(route('dashboard.option-groups.edit', $group));
        $this->assertSame(0, $group->min_select);
        $this->assertSame(3, $group->max_select);
        $this->assertFalse($group->is_required);
    }

    public function test_max_select_must_be_at_least_min_select(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.option-groups.store'), [
            'name' => 'Extras',
            'min_select' => 3,
            'max_select' => 1,
        ])->assertSessionHasErrors('max_select');
    }

    public function test_manager_can_update_an_option_group(): void
    {
        $manager = $this->makeManager();
        $group = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);

        $this->actingAs($manager)->put(route('dashboard.option-groups.update', $group), [
            'name' => 'Add-ons',
            'min_select' => 1,
            'max_select' => 3,
            'is_required' => '1',
        ])->assertRedirect(route('dashboard.option-groups.edit', $group));

        $group->refresh();
        $this->assertSame('Add-ons', $group->name);
        $this->assertTrue($group->is_required);
    }

    public function test_manager_can_add_update_and_remove_an_option(): void
    {
        $manager = $this->makeManager();
        $group = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);

        $this->actingAs($manager)->post(route('dashboard.option-groups.options.store', $group), [
            'name' => 'Extra Cheese',
            'price_delta' => '5.00',
        ])->assertRedirect(route('dashboard.option-groups.edit', $group));

        $option = Option::where('name', 'Extra Cheese')->first();
        $this->assertSame(500, $option->price_delta);

        $this->actingAs($manager)->put(route('dashboard.option-groups.options.update', [$group, $option]), [
            'name' => 'Extra Cheese',
            'price_delta' => '7.50',
            'is_active' => '1',
        ])->assertRedirect(route('dashboard.option-groups.edit', $group));

        $this->assertSame(750, $option->fresh()->price_delta);

        $this->actingAs($manager)->delete(route('dashboard.option-groups.options.destroy', [$group, $option]))
            ->assertRedirect(route('dashboard.option-groups.edit', $group));

        $this->assertSoftDeleted($option);
    }

    public function test_an_option_from_another_group_cannot_be_edited_through_this_group(): void
    {
        $manager = $this->makeManager();
        $groupA = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);
        $groupB = OptionGroup::create(['name' => 'Sides', 'min_select' => 0, 'max_select' => 1]);
        $option = Option::create(['option_group_id' => $groupA->id, 'name' => 'Extra Cheese', 'price_delta' => 500]);

        $this->actingAs($manager)->put(route('dashboard.option-groups.options.update', [$groupB, $option]), [
            'name' => 'Hacked',
            'price_delta' => '0',
        ])->assertNotFound();
    }
}
