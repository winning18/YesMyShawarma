<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\OptionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233243635265', 'address' => 'Ga Odumase, Accra',
            'lat' => 5.67, 'lng' => -0.30, 'opens_at' => '14:00', 'closes_at' => '00:00',
        ]);
    }

    public function test_home_page_renders_with_branches(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Ga Odumase');
    }

    public function test_branches_page_renders(): void
    {
        $this->get(route('branches.index'))->assertOk()->assertSee('Ga Odumase');
    }

    public function test_contact_page_renders(): void
    {
        $this->get(route('contact'))->assertOk()->assertSee('0243635265');
    }

    public function test_about_page_renders(): void
    {
        $this->get(route('about'))->assertOk();
    }

    public function test_selecting_a_branch_then_visiting_menu_shows_its_items(): void
    {
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => true]);

        $this->get(route('branches.pick', $this->branch))->assertRedirect(route('menu.index'));

        $this->get(route('menu.index'))->assertOk()->assertSee('Chicken Shawarma');
    }

    public function test_menu_without_a_selected_branch_redirects_to_branches(): void
    {
        $this->get(route('menu.index'))->assertRedirect(route('branches.index'));
    }

    public function test_menu_renders_categories_in_their_configured_order_regardless_of_item_order(): void
    {
        $drinks = Category::create(['name' => 'Drinks', 'slug' => 'drinks', 'sort_order' => 1]);
        $shawarma = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma', 'sort_order' => 0]);

        // Drinks' item has a lower sort_order than Shawarma's, despite
        // Shawarma being the category that should render first — this is
        // exactly the case that broke when category order came from a
        // flat item query grouped by name after the fact.
        $drinkItem = MenuItem::create([
            'category_id' => $drinks->id, 'name' => 'Mango', 'slug' => 'mango', 'base_price' => 2000, 'sort_order' => 0,
        ]);
        $shawarmaItem = MenuItem::create([
            'category_id' => $shawarma->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000, 'sort_order' => 5,
        ]);
        $this->branch->menuItems()->attach([$drinkItem->id, $shawarmaItem->id], ['is_available' => true]);

        $this->get(route('branches.pick', $this->branch));
        $response = $this->get(route('menu.index'));

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Drinks'),
            strpos($content, 'Shawarma'),
            'Expected the Shawarma section to render before the Drinks section.'
        );
    }

    public function test_selecting_an_inactive_branch_directly_is_rejected(): void
    {
        $this->branch->update(['is_active' => false]);

        $response = $this->get(route('branches.pick', $this->branch));

        $response->assertRedirect(route('branches.index'));
        $this->assertNull(session('customer_branch_id'));
    }

    public function test_menu_redirects_away_if_the_selected_branch_becomes_inactive(): void
    {
        $this->get(route('branches.pick', $this->branch));

        $this->branch->update(['is_active' => false]);

        $this->get(route('menu.index'))->assertRedirect(route('branches.index'));
    }

    public function test_option_groups_render_in_their_configured_pivot_order(): void
    {
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => true]);

        $extras = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 5]);
        $sauce = OptionGroup::create(['name' => 'Sauce', 'min_select' => 0, 'max_select' => 1]);

        // Attached out of the intended display order (Extras first, sort_order
        // 2) to make sure rendering actually follows the pivot value.
        $item->optionGroups()->attach([
            $extras->id => ['sort_order' => 2],
            $sauce->id => ['sort_order' => 1],
        ]);

        $this->get(route('branches.pick', $this->branch));
        $response = $this->get(route('menu.index'));

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Extras'),
            strpos($content, 'Sauce'),
            'Expected the Sauce group (sort_order 1) to render before Extras (sort_order 2).'
        );
    }
}
