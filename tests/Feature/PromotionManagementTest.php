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

    /**
     * Regression: $request->validate()'s 'integer' rule validates but
     * never casts — a real browser form POST sends every value as a
     * string, so without an explicit cast this column stored ["4"]
     * instead of [4]. PromotionService::findActiveAutomatic()'s own
     * strict in_array($today, ..., true) check (int vs string) then
     * never matched any day at all, regardless of what was configured —
     * the promotion silently never applied. Submitting recurring_days as
     * strings here (['3'], not [3]) is deliberate: passing a native PHP
     * int array, the way an earlier test in this file does, never
     * exercises this bug at all, since nothing coerces it to a string
     * the way a real form submission does.
     */
    public function test_recurring_days_are_stored_as_integers_not_strings(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)->post(route('dashboard.promotions.store'), [
            'code' => 'THURSDAY-AUTO',
            'type' => 'buy_x_get_y_free',
            'buy_quantity' => 2,
            'free_quantity' => 1,
            'is_automatic' => '1',
            'is_active' => '1',
            'recurring_days' => ['4'],
        ]);

        $promotion = Promotion::where('code', 'THURSDAY-AUTO')->first();

        $this->assertSame([4], $promotion->recurring_days);

        $today = now('Africa/Accra')->dayOfWeek;
        $promotion->update(['recurring_days' => ["{$today}"]]);

        $this->assertNotNull(app(\App\Services\Promotions\PromotionService::class)->findActiveAutomatic($this->branch));
    }

    /**
     * Regression: the Value field is only visually hidden for
     * buy_x_get_y_free (x-show), not removed from the DOM — before
     * x-bind:disabled was added, a real browser form submission still
     * included it with whatever was last rendered there. On any EDIT of
     * an existing buy_x_get_y_free promotion, that's the stored value
     * column, which is always 0 for this type (PromotionManagementController
     * ::valueInPesewas()) — "0" then failed the min:0.01 rule and
     * rejected the ENTIRE update, reverting every other field in the
     * same submission (recurring_days included) right along with it.
     * This is deliberately what made every single edit of an existing
     * automatic promotion look like "my changes don't save" — not an
     * edge case, the ordinary path.
     */
    public function test_updating_a_buy_x_get_y_free_promotion_with_a_stale_zero_value_still_saves(): void
    {
        $manager = $this->makeManager();
        $promotion = Promotion::create([
            'code' => 'STALE-VALUE', 'type' => 'buy_x_get_y_free', 'value' => 0,
            'buy_quantity' => 2, 'free_quantity' => 1, 'is_automatic' => true,
            'recurring_days' => [1], 'is_active' => true,
        ]);

        $this->actingAs($manager)->put(route('dashboard.promotions.update', $promotion), [
            'code' => 'STALE-VALUE',
            'type' => 'buy_x_get_y_free',
            'value' => '0',
            'buy_quantity' => 2,
            'free_quantity' => 1,
            'is_automatic' => '1',
            'is_active' => '1',
            'recurring_days' => [3],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame([3], $promotion->refresh()->recurring_days);
    }

    /**
     * Regression: unchecking every recurring_days checkbox while
     * "Applies automatically" stays checked fails validation
     * (required_if) server-side — correctly, since an automatic
     * promotion needs at least one day to ever apply — but with no
     * client-side guard, this rejected the ENTIRE update, reverting every
     * other field in the same submission (branches included) back to
     * whatever was already stored, which read as "unchecking doesn't
     * work" rather than a validation error on a specific field.
     */
    public function test_updating_with_no_recurring_days_fails_without_corrupting_the_promotion(): void
    {
        $manager = $this->makeManager();
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000003', 'address' => 'C',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $promotion = Promotion::create([
            'code' => 'MONDAY-AUTO', 'type' => 'buy_x_get_y_free', 'value' => 0,
            'buy_quantity' => 2, 'free_quantity' => 1, 'is_automatic' => true,
            'recurring_days' => [1], 'is_active' => true,
        ]);
        $promotion->branches()->sync([$this->branch->id, $otherBranch->id]);

        $this->actingAs($manager)->put(route('dashboard.promotions.update', $promotion), [
            'code' => 'MONDAY-AUTO',
            'type' => 'buy_x_get_y_free',
            'buy_quantity' => 2,
            'free_quantity' => 1,
            'is_automatic' => '1',
            'is_active' => '1',
            // recurring_days and branch_ids both omitted — simulates
            // unchecking every box in both groups before saving.
        ])->assertSessionHasErrors('recurring_days');

        $promotion->refresh();
        $this->assertSame([1], $promotion->recurring_days);
        $this->assertCount(2, $promotion->branches);
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
