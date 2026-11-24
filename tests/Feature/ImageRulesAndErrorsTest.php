<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Media\ImageRules;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Image sizes and the error standard (owner, 2026-11-24): every upload's
 * rules defined once and shown, big photos shrunk, small ones refused with
 * a message that says how to fix it, and failed forms marked in red.
 */
class ImageRulesAndErrorsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233241112222', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->owner = User::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->owner->assignRole('owner');
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function item(): MenuItem
    {
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);

        return MenuItem::create(['category_id' => $category->id, 'name' => 'Wrap', 'slug' => 'wrap', 'base_price' => 5000]);
    }

    public function test_every_upload_shows_its_size_rules(): void
    {
        $item = $this->item();

        $this->asOwner()->get(route('dashboard.menu-items.edit', $item))->assertOk()
            ->assertSee('Square (1:1) · best 1200×1200 px · at least 600×600 px · JPG, PNG or WebP · up to 4 MB', false)
            ->assertSee('Keep the dish in the middle');

        $this->asOwner()->get(route('dashboard.help.show', 'image-sizes'))->assertOk()
            ->assertSee('Hero photo (one per category)')->assertSee('1920×1080 px')->assertSee('deleted 24 hours after');
    }

    public function test_a_photo_too_small_is_refused_with_how_to_fix_it(): void
    {
        Storage::fake('public');
        $item = $this->item();

        $this->asOwner()->post(route('dashboard.menu-items.image.update', $item), [
            'image' => UploadedFile::fake()->image('small.jpg', 300, 300),
        ])->assertSessionHasErrors(['image' => 'The photo is too small: it would look blurred. Use one at least 600×600 px (best 1200×1200 px).']);

        $this->asOwner()->post(route('dashboard.menu-items.image.update', $item), [
            'image' => UploadedFile::fake()->create('big.jpg', 5000, 'image/jpeg'),
        ])->assertSessionHasErrors('image');

        $this->assertNull($item->fresh()->image_path);
    }

    public function test_a_big_photo_is_shrunk_to_its_best_size(): void
    {
        Storage::fake('public');
        $item = $this->item();

        $this->asOwner()->post(route('dashboard.menu-items.image.update', $item), [
            'image' => UploadedFile::fake()->image('huge.jpg', 3000, 2000),
        ])->assertSessionHasNoErrors();

        [$width, $height] = getimagesize(Storage::disk('public')->path($item->fresh()->image_path));
        $this->assertSame([1200, 800], [$width, $height], 'fits 1200×1200, shape kept');
    }

    public function test_a_wide_logo_only_needs_its_width(): void
    {
        $rules = app(ImageRules::class)->rules('logo');

        $this->assertContains('dimensions:min_width=400', $rules);
        $this->assertContains('max:2048', $rules);
    }

    public function test_row_uploads_show_their_mistake_on_that_row_only(): void
    {
        Storage::fake('public');
        $other = Branch::create([
            'name' => 'Lapaz', 'slug' => 'lapaz', 'phone' => '+233241113333', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);

        $this->asOwner()->from(route('dashboard.branches.index'))->post(route('dashboard.branches.image.update', $other), [
            'image' => UploadedFile::fake()->image('tiny.jpg', 100, 100),
        ])->assertSessionHasErrorsIn('branch-'.$other->id, 'image');
    }

    public function test_a_failed_form_hands_its_mistakes_to_the_page_in_plain_words(): void
    {
        $page = $this->asOwner()->from(route('dashboard.website-look.edit'))->followingRedirects()
            ->put(route('dashboard.website-look.update'), ['theme' => '']);

        $page->assertOk()
            ->assertSee('window.xposFormErrors', false)
            ->assertSee('1 thing needs fixing before this can be saved:')
            ->assertSee('Theme is missing. Fill it in and try again.');
    }

    public function test_validation_messages_say_how_to_fix_the_mistake(): void
    {
        $this->assertSame('That doesn\'t look like an email address. Check for a missing @ or dot (e.g. name@example.com).', __('validation.email', ['attribute' => 'email']));
        $this->assertStringContainsString('Fill it in', __('validation.required', ['attribute' => 'name']));
    }
}
