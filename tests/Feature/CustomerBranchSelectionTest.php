<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchWorkingHour;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBranchSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $odumase;

    private Branch $pokuase;

    private MenuItem $shawarma;

    private MenuItem $mango;

    // Roughly 15 km apart. NEAR_POKUASE is a few hundred metres from Pokuase.
    private const NEAR_POKUASE = ['lat' => 5.601, 'lng' => -0.201];

    protected function setUp(): void
    {
        parent::setUp();

        $this->odumase = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.70, 'lng' => -0.30, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);
        $this->pokuase = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.60, 'lng' => -0.20, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->shawarma = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $this->mango = MenuItem::create(['category_id' => $category->id, 'name' => 'Mango Juice', 'slug' => 'mango-juice', 'base_price' => 2000]);

        $this->odumase->menuItems()->attach([$this->shawarma->id, $this->mango->id], ['is_available' => true]);
        $this->pokuase->menuItems()->attach($this->shawarma->id, ['is_available' => true]);
    }

    private function addToCart(Branch $branch, MenuItem $item): void
    {
        $this->post(route('cart.add'), ['branch_id' => $branch->id, 'menu_item_id' => $item->id, 'quantity' => 1])->assertRedirect();
    }

    public function test_the_menu_says_when_the_branch_was_picked_for_the_customer(): void
    {
        $this->get(route('menu.index'))
            ->assertOk()
            ->assertSee('We picked')
            ->assertSee('Find nearest')
            ->assertCookieMissing(CustomerBranchSelection::COOKIE);
    }

    public function test_choosing_a_branch_is_remembered_on_the_device(): void
    {
        $this->get(route('branches.pick', $this->pokuase))
            ->assertRedirect(route('menu.index'))
            ->assertCookie(CustomerBranchSelection::COOKIE, (string) $this->pokuase->id);

        $this->get(route('menu.index'))->assertSee('Ordering from')->assertDontSee('We picked');

        // A later visit: new session, same device.
        $this->flushSession();
        $this->withCookie(CustomerBranchSelection::COOKIE, (string) $this->pokuase->id)
            ->get(route('menu.index'))
            ->assertSee('Menu: Pokuase')
            ->assertDontSee('We picked');
    }

    public function test_nothing_is_remembered_after_declining_cookies(): void
    {
        $this->withCookie('cookie_consent', 'decline')
            ->get(route('branches.pick', $this->pokuase))
            ->assertCookieMissing(CustomerBranchSelection::COOKIE);
    }

    public function test_a_remembered_branch_that_was_closed_down_is_ignored(): void
    {
        $this->pokuase->update(['is_active' => false]);

        $this->withCookie(CustomerBranchSelection::COOKIE, (string) $this->pokuase->id)
            ->get(route('menu.index'))
            ->assertSee('Menu: Ga Odumase')
            ->assertSee('We picked');
    }

    public function test_a_logged_in_customer_gets_their_last_orders_branch(): void
    {
        $customer = Customer::create(['phone' => '+233241234567', 'name' => 'Ama']);
        foreach ([[$this->odumase, now()->subWeek()], [$this->pokuase, now()->subDay()]] as [$branch, $placedAt]) {
            $order = Order::create([
                'reference' => 'ORD-'.$branch->id, 'track_token' => bin2hex(random_bytes(16)),
                'customer_id' => $customer->id, 'branch_id' => $branch->id, 'fulfilment_type' => 'pickup',
                'subtotal' => 5000, 'total' => 5000, 'payment_method' => 'cash', 'payment_status' => 'paid',
            ]);
            $order->placed_at = $placedAt;
            $order->save();
        }

        $this->actingAs($customer, 'customer')
            ->get(route('menu.index'))
            ->assertSee('Menu: Pokuase')
            ->assertDontSee('We picked');
    }

    public function test_find_nearest_selects_the_closest_open_branch(): void
    {
        $this->postJson(route('branches.nearest'), [...self::NEAR_POKUASE, 'apply' => true])
            ->assertOk()
            ->assertJsonPath('branch.name', 'Pokuase')
            ->assertJsonPath('closer_closed', null)
            ->assertCookie(CustomerBranchSelection::COOKIE, (string) $this->pokuase->id);

        $this->get(route('menu.index'))
            ->assertSee('Menu: Pokuase')
            ->assertSee('your nearest branch')
            ->assertDontSee('We picked');
    }

    public function test_find_nearest_skips_a_branch_that_is_not_taking_orders(): void
    {
        $this->pokuase->update(['is_accepting_orders' => false]);

        $this->postJson(route('branches.nearest'), [...self::NEAR_POKUASE, 'apply' => true])
            ->assertJsonPath('branch.name', 'Ga Odumase')
            ->assertJsonPath('closer_closed', 'Pokuase');

        $this->get(route('menu.index'))->assertSee('Pokuase is closed right now');
    }

    public function test_find_nearest_skips_a_branch_closed_by_its_working_hours(): void
    {
        // Every day configured as closed for Pokuase only.
        foreach (range(1, 7) as $day) {
            BranchWorkingHour::create(['branch_id' => $this->pokuase->id, 'day_of_week' => $day, 'opens_at' => null, 'closes_at' => null]);
        }

        $this->postJson(route('branches.nearest'), self::NEAR_POKUASE)
            ->assertJsonPath('branch.name', 'Ga Odumase')
            ->assertJsonPath('closer_closed', 'Pokuase');
    }

    public function test_when_every_branch_is_closed_the_nearest_one_taking_orders_is_used(): void
    {
        foreach ([$this->odumase, $this->pokuase] as $branch) {
            foreach (range(1, 7) as $day) {
                BranchWorkingHour::create(['branch_id' => $branch->id, 'day_of_week' => $day, 'opens_at' => null, 'closes_at' => null]);
            }
        }

        $this->postJson(route('branches.nearest'), self::NEAR_POKUASE)
            ->assertJsonPath('branch.name', 'Pokuase')
            ->assertJsonPath('closer_closed', null);
    }

    public function test_find_nearest_without_apply_changes_nothing(): void
    {
        $this->get(route('branches.pick', $this->odumase));

        $this->postJson(route('branches.nearest'), self::NEAR_POKUASE)->assertJsonPath('branch.name', 'Pokuase');

        $this->get(route('menu.index'))->assertSee('Menu: Ga Odumase');
    }

    public function test_switching_branch_moves_the_cart_and_lists_what_was_removed(): void
    {
        $this->get(route('branches.pick', $this->odumase));
        $this->addToCart($this->odumase, $this->shawarma);
        $this->addToCart($this->odumase, $this->mango);

        $this->get(route('branches.pick', $this->pokuase))
            ->assertSessionHas('status', "You're ordering from Pokuase. Mango Juice isn't available at Pokuase and was removed from your cart.");

        $this->get(route('cart.show'))
            ->assertSee('Chicken Shawarma')
            ->assertSee('Cart (1)')
            ->assertSee('Ordering from');
    }

    public function test_checkout_suggests_a_branch_that_is_clearly_closer(): void
    {
        $this->get(route('branches.pick', $this->odumase));
        $this->addToCart($this->odumase, $this->shawarma);

        $this->postJson(route('branches.nearest'), self::NEAR_POKUASE)
            ->assertJsonPath('suggest_switch', true)
            ->assertJsonPath('current.name', 'Ga Odumase');

        // A pin near the cart's own branch: nothing to suggest.
        $this->postJson(route('branches.nearest'), ['lat' => 5.699, 'lng' => -0.299])
            ->assertJsonPath('suggest_switch', false);

        // Halfway-ish, Pokuase under 2 km closer: not worth suggesting.
        $this->postJson(route('branches.nearest'), ['lat' => 5.645, 'lng' => -0.245])
            ->assertJsonPath('branch.name', 'Pokuase')
            ->assertJsonPath('suggest_switch', false);
    }

    public function test_moving_the_order_from_checkout_returns_to_checkout(): void
    {
        $this->get(route('branches.pick', $this->odumase));
        $this->addToCart($this->odumase, $this->shawarma);

        $this->get(route('branches.pick', [$this->pokuase, 'return' => 'checkout']))
            ->assertRedirect(route('checkout.show'));

        $this->get(route('checkout.show'))->assertOk()->assertSee('Chicken Shawarma');
    }

    public function test_the_api_reports_the_nearest_branch(): void
    {
        $this->getJson(route('api.branches.nearest', self::NEAR_POKUASE))
            ->assertOk()
            ->assertJsonPath('data.branch.name', 'Pokuase');

        $this->getJson(route('api.branches.nearest'))->assertUnprocessable();
    }
}
