<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Services\Images\WebpConverter;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebpConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_uploading_an_image_generates_its_webp_sibling_immediately(): void
    {
        $path = app(ImageUploadService::class)->store('menu-items', 1, UploadedFile::fake()->image('item.jpg'), null);

        Storage::disk('public')->assertExists(preg_replace('/\.jpg$/', '.webp', $path));
    }

    public function test_deleting_an_image_removes_its_webp_sibling_too(): void
    {
        $images = app(ImageUploadService::class);
        $path = $images->store('menu-items', 1, UploadedFile::fake()->image('item.jpg'), null);
        $webpPath = preg_replace('/\.jpg$/', '.webp', $path);
        Storage::disk('public')->assertExists($webpPath);

        $images->delete($path);

        Storage::disk('public')->assertMissing($webpPath);
    }

    public function test_url_for_returns_null_when_no_webp_sibling_exists_yet(): void
    {
        Storage::disk('public')->put('menu-items/2.jpg', 'not-a-real-image-just-a-placeholder');

        $this->assertNull(app(WebpConverter::class)->urlFor('menu-items/2.jpg'));
    }

    public function test_generate_is_a_harmless_no_op_for_an_unsupported_or_corrupt_file(): void
    {
        Storage::disk('public')->put('menu-items/3.jpg', 'not-a-real-image-just-a-placeholder');

        app(WebpConverter::class)->generate('menu-items/3.jpg');

        Storage::disk('public')->assertMissing('menu-items/3.webp');
    }

    public function test_generate_ignores_a_null_path(): void
    {
        app(WebpConverter::class)->generate(null);

        $this->assertTrue(true);
    }

    public function test_backfill_command_generates_webp_for_every_existing_image(): void
    {
        $category = \App\Models\Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma',
            'base_price' => 3500,
        ]);
        app(ImageUploadService::class)->store('menu-items', $item->id, UploadedFile::fake()->image('item.jpg'), null);
        // Simulate an image uploaded before this feature existed — its
        // .webp sibling was never generated, only the backfill command
        // should be able to catch it up.
        $item->update(['image_path' => 'menu-items/'.$item->id.'.jpg']);
        Storage::disk('public')->delete('menu-items/'.$item->id.'.webp');
        Storage::disk('public')->assertMissing('menu-items/'.$item->id.'.webp');

        $this->artisan('images:generate-webp')->assertSuccessful();

        Storage::disk('public')->assertExists('menu-items/'.$item->id.'.webp');
    }
}
