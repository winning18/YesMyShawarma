<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchWorkingHour;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\OptionGroup;
use App\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    public function test_home_page_marquee_links_to_an_available_item_when_a_branch_is_selected(): void
    {
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma',
            'base_price' => 3500, 'sort_order' => 1,
        ]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => true]);

        $response = $this->withSession(['customer_branch_id' => $this->branch->id])->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('menu.show', $item), false);
        $response->assertDontSee('Sold out');
    }

    // Regression: the marquee used to link every globally-active item
    // straight to its product page regardless of branch availability —
    // clicking a sold-out item 404'd on a live server, since
    // MenuController@show 404s on anything not available at the
    // customer's selected branch. It must stay visible (grayed out, no
    // link) instead of producing a dead link.
    public function test_home_page_marquee_grays_out_an_item_unavailable_at_the_selected_branch(): void
    {
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma',
            'base_price' => 3500, 'sort_order' => 1,
        ]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => false]);

        $response = $this->withSession(['customer_branch_id' => $this->branch->id])->get(route('home'));

        $response->assertOk();
        $response->assertSee('Chicken Shawarma');
        $response->assertSee('Sold out');
        $response->assertDontSee(route('menu.show', $item), false);
    }

    // Regression: HomeController used to mark each item's availability
    // via Collection::each(), which stops iterating the instant a
    // callback returns exactly false — and isAvailable=false (the
    // sold-out case) IS exactly false. A sold-out item ordered before
    // others silently cut the loop short, leaving every later item's
    // isAvailable unset (falsy in Blade) — the entire rest of the
    // category rendered grayed out instead of just the one sold-out item.
    public function test_home_page_marquee_only_grays_out_the_sold_out_item_not_every_item_after_it(): void
    {
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $soldOut = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma',
            'base_price' => 3500, 'sort_order' => 1,
        ]);
        $stillAvailable = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Beef Shawarma', 'slug' => 'beef-shawarma',
            'base_price' => 4000, 'sort_order' => 2,
        ]);
        $this->branch->menuItems()->attach($soldOut->id, ['is_available' => false]);
        $this->branch->menuItems()->attach($stillAvailable->id, ['is_available' => true]);

        $response = $this->withSession(['customer_branch_id' => $this->branch->id])->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('menu.show', $stillAvailable), false);
        $response->assertDontSee(route('menu.show', $soldOut), false);
    }

    public function test_home_page_marquee_shows_items_as_available_when_no_branch_is_selected_yet(): void
    {
        // No branch chosen yet — availability is inherently unknown
        // (branch_menu_item is per-branch), so every globally-active item
        // still shows a working link; MenuController@show's own
        // resolveBranch sends the visitor to pick a branch first rather
        // than 404ing.
        $category = Category::create(['name' => 'Shawarma', 'slug' => 'shawarma']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma',
            'base_price' => 3500, 'sort_order' => 1,
        ]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('menu.show', $item), false);
        $response->assertDontSee('Sold out');
    }

    public function test_branches_page_renders(): void
    {
        $this->get(route('branches.index'))->assertOk()->assertSee('Ga Odumase');
    }

    public function test_branches_page_shows_accepting_orders_when_open(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(15, 0));
        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);

        $this->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Accepting orders')
            ->assertDontSee('Closed — opens');
    }

    public function test_branches_page_shows_closed_with_opening_time_when_outside_working_hours(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(8, 0));
        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);

        $this->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Closed — opens Monday 10:00am')
            ->assertDontSee('Accepting orders');
    }

    public function test_branches_page_shows_paused_regardless_of_working_hours(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(15, 0));
        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);
        $this->branch->update(['is_accepting_orders' => false]);

        $this->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Not accepting orders right now')
            ->assertDontSee('Accepting orders')
            ->assertDontSee('Closed — opens');
    }

    // Regression: the branch card used to print Branch::opens_at/closes_at
    // directly — a flat, same-every-day seed value with no admin editor —
    // instead of the branch's actual admin-configurable weekly schedule
    // (the "Working Hours" dashboard page, WorkingHoursService). The
    // branch was seeded with opens_at=14:00/closes_at=00:00 here on
    // purpose (see setUp) so this test fails loudly if that flat value
    // ever leaks back onto the page.
    public function test_branches_page_shows_todays_admin_set_hours_not_the_flat_seeded_columns(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(15, 0));
        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);

        $response = $this->get(route('branches.index'))->assertOk();

        $response->assertSee('Hours today');
        $response->assertSee('10:00am');
        $response->assertSee('10:00pm');
        // The flat columns this branch was seeded with (setUp: 14:00/00:00)
        // must never appear — that would mean the old hardcoded path won.
        $response->assertDontSee('2:00pm');
        $response->assertDontSee('12:00am');
    }

    public function test_branches_page_hides_the_hours_line_when_no_working_hours_are_configured_yet(): void
    {
        // No BranchWorkingHour rows at all for $this->branch — the
        // "Working Hours" page has never been touched for it. Nothing
        // admin-set exists to show, so the line should be omitted rather
        // than falling back to a guess.
        $this->get(route('branches.index'))
            ->assertOk()
            ->assertDontSee('Hours today');
    }

    public function test_branches_page_hides_the_hours_line_on_a_day_the_branch_is_marked_closed(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(15, 0));
        // Both null — schema.md's documented "closed that day" signal.
        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => null, 'closes_at' => null]);

        $this->get(route('branches.index'))
            ->assertOk()
            ->assertDontSee('Hours today');
    }

    public function test_the_currently_selected_branch_is_highlighted_on_the_branches_page(): void
    {
        $otherBranch = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'Pokuase, Accra',
            'lat' => 5.7, 'lng' => -0.29, 'opens_at' => '19:00', 'closes_at' => '00:00',
        ]);

        // this->branch ("Ga Odumase") is selected, not the other branch —
        // assert the badge sits between the two branch cards in the
        // rendered HTML, i.e. attached to Ga Odumase's card specifically
        // rather than merely present somewhere on the page. Positions are
        // measured from the start of the card grid, not the whole page —
        // the search bar above it embeds every branch's name/address
        // up front (client-side search index, same pattern as the menu
        // page), which would otherwise make the other branch's name look
        // like it precedes the badge even though its actual card doesn't.
        $this->get(route('branches.pick', $this->branch));

        $content = $this->get(route('branches.index'))->assertOk()->getContent();
        $gridStart = strpos($content, 'grid grid-cols-1 md:grid-cols-2');

        $selectedNamePos = strpos($content, $this->branch->name, $gridStart);
        $badgePos = strpos($content, 'Currently selected', $gridStart);
        $otherNamePos = strpos($content, $otherBranch->name, $gridStart);

        $this->assertNotFalse($badgePos, 'Expected the "Currently selected" badge to be present.');
        $this->assertGreaterThan($selectedNamePos, $badgePos, 'Expected the badge to render after the selected branch\'s name.');
        $this->assertLessThan($otherNamePos, $badgePos, 'Expected the badge to render before the other, unselected branch\'s section.');
    }

    public function test_contact_page_renders(): void
    {
        $this->get(route('contact'))->assertOk()->assertSee('+233 (0) 243 635 265');
    }

    public function test_contact_page_shows_closed_with_opening_time_when_outside_working_hours(): void
    {
        Carbon::setTestNow(Carbon::now('Africa/Accra')->next(Carbon::MONDAY)->setTime(8, 0));
        BranchWorkingHour::create(['branch_id' => $this->branch->id, 'day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '22:00']);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Closed — opens Monday 10:00am')
            ->assertDontSee('Accepting orders');
    }

    public function test_about_page_renders(): void
    {
        $this->get(route('about'))->assertOk();
    }

    public function test_about_page_hides_meet_our_staff_when_no_staff_members_exist(): void
    {
        $this->get(route('about'))->assertOk()->assertDontSee('Meet our staff');
    }

    public function test_about_page_shows_active_staff_members_in_sort_order(): void
    {
        StaffMember::create(['name' => 'Zoe Mensah', 'title' => 'Rider', 'sort_order' => 2, 'is_active' => true]);
        StaffMember::create(['name' => 'Ama Owusu', 'title' => 'Branch Manager', 'sort_order' => 1, 'is_active' => true]);
        StaffMember::create(['name' => 'Kojo Boateng', 'title' => 'Retired', 'sort_order' => 0, 'is_active' => false]);

        $content = $this->get(route('about'))->assertOk()
            ->assertSee('Meet our staff')
            ->assertSee('Ama Owusu')
            ->assertSee('Branch Manager')
            ->assertSee('Zoe Mensah')
            ->assertDontSee('Kojo Boateng')
            ->getContent();

        $this->assertLessThan(strpos($content, 'Zoe Mensah'), strpos($content, 'Ama Owusu'));
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
