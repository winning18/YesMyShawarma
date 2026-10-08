<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

    public function test_manager_can_create_an_automatic_buy_x_get_y_free_promotion(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'wednesday-auto',
            'type' => 'buy_x_get_y_free',
            'buy_quantity' => 3,
            'free_quantity' => 1,
            'is_automatic' => '1',
            'recurring_days' => [3],
            'banner_headline' => 'Buy 2 Get 1 Free — every Wednesday!',
        ])->assertRedirect(route('dashboard.promotions.index'));

        $promotion = Promotion::where('code', 'WEDNESDAY-AUTO')->first();

        $this->assertSame('buy_x_get_y_free', $promotion->type);
        $this->assertSame(0, $promotion->value);
        $this->assertSame(3, $promotion->buy_quantity);
        $this->assertSame(1, $promotion->free_quantity);
        $this->assertTrue($promotion->is_automatic);
        $this->assertSame([3], $promotion->recurring_days);
        $this->assertSame('Buy 2 Get 1 Free — every Wednesday!', $promotion->banner_headline);
    }

    public function test_buy_x_get_y_free_requires_buy_and_free_quantities(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'INCOMPLETE',
            'type' => 'buy_x_get_y_free',
        ])->assertSessionHasErrors(['buy_quantity', 'free_quantity']);
    }

    public function test_an_automatic_promotion_requires_at_least_one_recurring_day(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'NODAYS',
            'type' => 'percentage',
            'value' => 10,
            'is_automatic' => '1',
        ])->assertSessionHasErrors('recurring_days');
    }

    /**
     * Regression: the banner-image upload/remove forms on the edit page
     * must never be nested inside the main update <form> — a <form>
     * nested inside another is invalid HTML, and a browser silently
     * closes the OUTER form the instant it hits the inner one's closing
     * tag, stranding the Save button outside any form at all (it does
     * nothing when clicked). A PHPUnit request to the controller can't
     * catch this on its own, since nothing here actually parses/corrects
     * HTML the way a browser does — this only catches it by checking the
     * raw markup's own ordering.
     */
    public function test_the_save_button_is_not_stranded_outside_the_form_by_a_nested_banner_upload_form(): void
    {
        $manager = $this->makeManager();
        $promotion = Promotion::create([
            'code' => 'WEDNESDAY-AUTO', 'type' => 'buy_x_get_y_free', 'value' => 0,
            'buy_quantity' => 3, 'free_quantity' => 1, 'is_automatic' => true, 'recurring_days' => [3],
        ]);

        $html = $this->actingAs($manager)->get(route('dashboard.promotions.edit', $promotion))->getContent();

        $updateFormOpen = strpos($html, 'action="'.route('dashboard.promotions.update', $promotion).'"');
        $updateFormClose = strpos($html, '</form>', $updateFormOpen);
        $bannerFormOpen = strpos($html, 'action="'.route('dashboard.promotions.banner-image.update', $promotion).'"');

        $this->assertNotFalse($updateFormOpen);
        $this->assertNotFalse($bannerFormOpen);
        $this->assertLessThan($bannerFormOpen, $updateFormClose, 'The update form must close before the banner upload form opens.');
    }

    public function test_manager_can_upload_and_remove_a_promotion_banner_image(): void
    {
        $manager = $this->makeManager();
        $promotion = Promotion::create([
            'code' => 'WEDNESDAY-AUTO', 'type' => 'buy_x_get_y_free', 'value' => 0,
            'buy_quantity' => 3, 'free_quantity' => 1, 'is_automatic' => true, 'recurring_days' => [3],
        ]);

        $this->actingAs($manager)
            ->post(route('dashboard.promotions.banner-image.update', $promotion), [
                'image' => UploadedFile::fake()->image('banner.jpg'),
            ])
            ->assertRedirect();

        $this->assertNotNull($promotion->refresh()->banner_image_path);

        $this->actingAs($manager)
            ->delete(route('dashboard.promotions.banner-image.destroy', $promotion))
            ->assertRedirect();

        $this->assertNull($promotion->refresh()->banner_image_path);
    }
}
