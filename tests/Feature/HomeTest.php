<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_and_shows_a_menu_strip_for_an_available_item(): void
    {
        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 3500,
        ]);
        $branch->menuItems()->attach($item->id, ['is_available' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Shawarma favourites');
        $response->assertSee('Chicken Shawarma');
        // Zero-JS scroll-snap strip, not the old auto-scrolling menuSlider()
        // Alpine component — see home.blade.php's own comment for why.
        $response->assertSee('snap-x snap-mandatory', false);
        $response->assertDontSee('menuSlider(', false);
    }

    public function test_home_page_loads_with_no_branches_or_menu_items_at_all(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }
}
