<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $shawarma;

    private Option $chiliSauce;

    private Option $cheese;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);

        $this->shawarma = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->branch->menuItems()->attach($this->shawarma->id, ['is_available' => true]);

        $sauceGroup = OptionGroup::create(['name' => 'Sauce', 'min_select' => 0, 'max_select' => 1, 'is_required' => false]);
        $this->chiliSauce = Option::create(['option_group_id' => $sauceGroup->id, 'name' => 'Chili', 'price_delta' => 200]);
        $this->shawarma->optionGroups()->attach($sauceGroup->id, ['sort_order' => 1]);

        $extrasGroup = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);
        $this->cheese = Option::create(['option_group_id' => $extrasGroup->id, 'name' => 'Cheese', 'price_delta' => 300]);
        $this->shawarma->optionGroups()->attach($extrasGroup->id, ['sort_order' => 2]);
    }

    public function test_add_and_view_cart(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 2,
            'option_ids' => [$this->chiliSauce->id],
        ])->assertRedirect();

        $response = $this->get(route('cart.show'));

        $response->assertOk();
        $response->assertSee('Chicken Shawarma');
        // (5000 + 200) * 2 = 10400
        $response->assertSee('104.00');
    }

    public function test_update_quantity(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 1,
            'option_ids' => [],
        ]);

        $lineId = $this->extractLineId();

        $this->patch(route('cart.update', $lineId), ['quantity' => 3])->assertRedirect();

        $this->get(route('cart.show'))->assertSee('150.00'); // 5000 * 3
    }

    public function test_remove_line(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 1,
            'option_ids' => [],
        ]);

        $lineId = $this->extractLineId();

        $this->delete(route('cart.remove', $lineId))->assertRedirect();

        $this->get(route('cart.show'))->assertSee('Your cart is empty');
    }

    public function test_adding_from_a_different_branch_replaces_the_cart(): void
    {
        $otherBranch = Branch::create([
            'name' => 'East Legon', 'slug' => 'east-legon', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.6, 'lng' => -0.2, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
        $otherBranch->menuItems()->attach($this->shawarma->id, ['is_available' => true]);

        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1, 'option_ids' => [],
        ]);
        $this->post(route('cart.add'), [
            'branch_id' => $otherBranch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1, 'option_ids' => [],
        ]);

        $response = $this->get(route('cart.show'));
        $response->assertSee('East Legon');
    }

    public function test_adding_the_same_item_and_options_twice_merges_into_one_line(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1, 'option_ids' => [$this->chiliSauce->id],
        ]);
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 2, 'option_ids' => [$this->chiliSauce->id],
        ]);

        $cart = session('cart');

        $this->assertCount(1, $cart['items']);
        $this->assertSame(3, $cart['items'][0]['quantity']);
    }

    public function test_adding_the_same_item_with_different_notes_stays_separate(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1, 'option_ids' => [], 'notes' => 'No onions',
        ]);
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1, 'option_ids' => [],
        ]);

        $this->assertCount(2, session('cart')['items']);
    }

    public function test_merging_caps_at_the_max_line_quantity(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 15, 'option_ids' => [],
        ]);
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 10, 'option_ids' => [],
        ]);

        $this->assertSame(20, session('cart')['items'][0]['quantity']);
    }

    public function test_quantity_above_the_cap_is_rejected(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 21, 'option_ids' => [],
        ])->assertSessionHasErrors('quantity');
    }

    public function test_menu_item_from_a_deactivated_branch_cannot_be_added(): void
    {
        $this->branch->update(['is_active' => false]);

        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1, 'option_ids' => [],
        ])->assertSessionHasErrors('menu_item_id');

        $this->assertNull(session('cart'));
    }

    public function test_a_multi_select_options_quantity_prices_as_a_fixed_total_for_the_line(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 2,
            'option_ids' => [$this->cheese->id],
            'option_qty' => [$this->cheese->id => 2],
        ])->assertRedirect();

        // 5000 * 2 + 300 * 2 = 10600 — cheese priced once for the whole
        // line, not scaled by the item's own quantity of 2.
        $this->get(route('cart.show'))->assertSee('106.00');
    }

    public function test_a_quantity_submitted_for_a_single_select_option_is_ignored(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 2,
            'option_ids' => [$this->chiliSauce->id],
            'option_qty' => [$this->chiliSauce->id => 5],
        ])->assertRedirect();

        // (5000 + 200) * 2 = 10400 — chili is single-select, so its
        // quantity is always forced to 1 regardless of what was sent.
        $this->get(route('cart.show'))->assertSee('104.00');
    }

    public function test_updating_an_options_quantity_recalculates_the_line_total(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 1,
            'option_ids' => [$this->cheese->id],
            'option_qty' => [$this->cheese->id => 1],
        ]);

        $lineId = $this->extractLineId();

        $this->patch(route('cart.options.update', [$lineId, $this->cheese->id]), ['quantity' => 3])
            ->assertRedirect();

        // 5000 + 300 * 3 = 5900
        $this->get(route('cart.show'))->assertSee('59.00');
    }

    public function test_different_option_quantities_stay_separate_lines(): void
    {
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1,
            'option_ids' => [$this->cheese->id], 'option_qty' => [$this->cheese->id => 1],
        ]);
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id, 'menu_item_id' => $this->shawarma->id, 'quantity' => 1,
            'option_ids' => [$this->cheese->id], 'option_qty' => [$this->cheese->id => 2],
        ]);

        $this->assertCount(2, session('cart')['items']);
    }

    private function extractLineId(): string
    {
        $cart = session('cart');

        return $cart['items'][0]['id'];
    }
}
