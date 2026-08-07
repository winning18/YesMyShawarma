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

    public function test_home_page_renders(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_home_page_hero_only_shows_categories_with_an_uploaded_photo(): void
    {
        Category::create(['name' => 'Shawarma', 'slug' => 'shawarma', 'hero_image_path' => 'category-hero/1.jpg']);
        Category::create(['name' => 'Salads', 'slug' => 'salads']);

        $response = $this->get(route('home'));

        $response->assertSee('Shawarma');
        $response->assertDontSee('Salads');
    }

    public function test_home_page_hero_excludes_inactive_categories(): void
    {
        Category::create([
            'name' => 'Discontinued Wraps', 'slug' => 'discontinued-wraps',
            'hero_image_path' => 'category-hero/1.jpg', 'is_active' => false,
        ]);

        $this->get(route('home'))->assertDontSee('Discontinued Wraps');
    }

    public function test_home_page_hero_shows_the_categorys_tagline(): void
    {
        Category::create([
            'name' => 'Shawarma', 'slug' => 'shawarma', 'hero_image_path' => 'category-hero/1.jpg',
            'tagline' => 'Wrapped fresh, packed with flavour.',
        ]);

        $this->get(route('home'))->assertSee('Wrapped fresh, packed with flavour.');
    }

    public function test_branches_page_renders(): void
    {
        $this->get(route('branches.index'))->assertOk()->assertSee('Ga Odumase');
    }

    public function test_the_currently_selected_branch_is_highlighted_on_the_branches_page(): void
    {
        $otherBranch = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'Pokuase, Accra',
            'lat' => 5.7, 'lng' => -0.29, 'opens_at' => '19:00', 'closes_at' => '00:00',
        ]);

        // this->branch ("Ga Odumase") is selected, not the other branch —
        // assert the badge sits between the two branch names in the
        // rendered HTML, i.e. attached to Ga Odumase's section specifically
        // rather than merely present somewhere on the page.
        $this->get(route('branches.pick', $this->branch));

        $content = $this->get(route('branches.index'))->assertOk()->getContent();

        $selectedNamePos = strpos($content, $this->branch->name);
        $badgePos = strpos($content, 'Currently selected');
        $otherNamePos = strpos($content, $otherBranch->name);

        $this->assertNotFalse($badgePos, 'Expected the "Currently selected" badge to be present.');
        $this->assertGreaterThan($selectedNamePos, $badgePos, 'Expected the badge to render after the selected branch\'s name.');
        $this->assertLessThan($otherNamePos, $badgePos, 'Expected the badge to render before the other, unselected branch\'s section.');
    }

    public function test_contact_page_renders(): void
    {
        $this->get(route('contact'))->assertOk()->assertSee('+233 (0) 243 635 265');
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

    public function test_product_page_shows_the_item_its_extras_and_drinks_as_optional_addons(): void
    {
        $shawarma = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $drinks = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);

        $item = MenuItem::create([
            'category_id' => $shawarma->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $drink = MenuItem::create([
            'category_id' => $drinks->id, 'name' => 'Mango', 'slug' => 'mango', 'base_price' => 2000,
        ]);
        $this->branch->menuItems()->attach([$item->id, $drink->id], ['is_available' => true]);

        $extras = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 5]);
        $item->optionGroups()->attach($extras->id, ['sort_order' => 1]);

        $this->get(route('branches.pick', $this->branch));

        $this->get(route('menu.show', $item))
            ->assertOk()
            ->assertSee('Chicken Shawarma')
            ->assertSee('Extras')
            ->assertSee('Drinks')
            ->assertSee('Mango');
    }

    public function test_product_page_excludes_itself_from_its_own_drinks_addon_list(): void
    {
        $drinks = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        $mango = MenuItem::create([
            'category_id' => $drinks->id, 'name' => 'Mango', 'slug' => 'mango', 'base_price' => 2000,
        ]);
        $orange = MenuItem::create([
            'category_id' => $drinks->id, 'name' => 'Orange', 'slug' => 'orange', 'base_price' => 2000,
        ]);
        $this->branch->menuItems()->attach([$mango->id, $orange->id], ['is_available' => true]);

        $this->get(route('branches.pick', $this->branch));
        $response = $this->get(route('menu.show', $mango));

        $response->assertOk()->assertSee('Orange');

        // Only Orange should get a "+ Add" quick-add card in the Drinks
        // cross-sell section — Mango appearing there too (as well as in
        // the page's own title/heading) would mean the "exclude itself"
        // filter isn't working.
        $this->assertSame(1, substr_count($response->getContent(), '+ Add'));
    }

    public function test_product_page_without_a_selected_branch_redirects_to_branches(): void
    {
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);

        $this->get(route('menu.show', $item))->assertRedirect(route('branches.index'));
    }

    public function test_product_page_404s_when_unavailable_at_the_selected_branch(): void
    {
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => false]);

        $this->get(route('branches.pick', $this->branch));

        $this->get(route('menu.show', $item))->assertNotFound();
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
