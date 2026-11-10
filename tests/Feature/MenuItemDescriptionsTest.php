<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_menu_item_has_owner_approved_copy_that_fits_a_search_snippet(): void
    {
        $descriptions = require database_path('seeders/data/menu_item_descriptions.php');
        $menu = require database_path('seeders/data/menu.php');
        $slugs = collect($menu)->flatMap(fn ($c) => collect($c['items'])->map(fn ($i) => str($i['name'])->slug()->toString()));

        $this->assertSame([], $slugs->diff(array_keys($descriptions))->values()->all(), 'items without a description');

        foreach ($descriptions as $slug => $description) {
            $this->assertLessThanOrEqual(150, mb_strlen($description), $slug);
        }
    }

    public function test_seeding_fills_empty_descriptions_but_never_overwrites_one_written_in_the_dashboard(): void
    {
        Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $this->seed(MenuSeeder::class);
        MenuItem::where('slug', 'beef-burger')->update(['description' => 'Written by the owner']);
        MenuItem::where('slug', 'hot-dog')->update(['description' => null]);

        $this->seed(MenuSeeder::class);

        $this->assertSame('Written by the owner', MenuItem::where('slug', 'beef-burger')->value('description'));
        $this->assertSame('A sausage hot dog in a soft bun. Quick, tasty, budget-friendly and easy to eat on the go.', MenuItem::where('slug', 'hot-dog')->value('description'));
        $this->assertSame(0, MenuItem::whereNull('description')->count());
    }
}
