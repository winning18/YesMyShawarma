<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PosOrderTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $shawarma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);

        $this->shawarma = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000,
        ]);
        $this->branch->menuItems()->attach($this->shawarma->id, ['is_available' => true]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create();
        $this->assignRoleAt($staff, 'staff', $this->branch);

        return $staff;
    }

    private function addToPosCart(User $staff, int $quantity = 1): void
    {
        $this->actingAs($staff)->postJson(route('dashboard.pos.cart.add'), [
            'menu_item_id' => $this->shawarma->id,
            'quantity' => $quantity,
            'option_ids' => [],
        ]);
    }

    public function test_staff_can_view_the_pos_page(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->get(route('dashboard.pos.index'))
            ->assertOk()
            ->assertSee('Chicken Shawarma');
    }

    public function test_pos_page_includes_the_order_alert_widget_for_the_current_branch(): void
    {
        $staff = $this->makeStaff();

        $response = $this->actingAs($staff)->get(route('dashboard.pos.index'));

        // Staff working POS has no reason to be looking at the Orders
        // board — this is what tells them a web order just came in
        // without switching tabs (real-time alert + sound, not just the
        // page itself refreshing).
        $response->assertSee("orderAlertWidget({$this->branch->id})", false);
    }

    public function test_rider_cannot_view_the_pos_page(): void
    {
        $rider = User::factory()->create();
        $this->assignRoleAt($rider, 'rider', $this->branch);

        $this->actingAs($rider)->get(route('dashboard.pos.index'))->assertForbidden();
    }

    public function test_owner_is_redirected_away_from_pos_to_the_business_overview(): void
    {
        // POS is not an owner feature at all, regardless of branch
        // selection state — unlike every other multi-branch role, owner
        // never gets sent to pick one for POS's sake, because they never
        // reach POS in the first place.
        $owner = User::factory()->create();
        $this->assignRoleAt($owner, 'owner', $this->branch);

        $this->actingAs($owner)->get(route('dashboard.pos.index'))
            ->assertRedirect(route('dashboard.performance'));
    }

    public function test_adding_an_item_returns_the_updated_cart_summary(): void
    {
        $staff = $this->makeStaff();

        $response = $this->actingAs($staff)->postJson(route('dashboard.pos.cart.add'), [
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 2,
            'option_ids' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('subtotal', 10000);
        $response->assertJsonCount(1, 'lines');
        $response->assertJsonPath('lines.0.quantity', 2);
    }

    public function test_pos_cart_is_isolated_from_the_customer_cart(): void
    {
        $staff = $this->makeStaff();

        // Guest customer cart, same test-client session.
        $this->post(route('cart.add'), [
            'branch_id' => $this->branch->id,
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 1,
            'option_ids' => [],
        ]);

        // Staff POS cart, same session — must not merge with the above.
        $this->addToPosCart($staff, 3);

        $customerCart = $this->get(route('cart.show'));
        $customerCart->assertViewHas('lines', fn ($lines) => count($lines) === 1 && $lines[0]['quantity'] === 1);

        $posPage = $this->actingAs($staff)->get(route('dashboard.pos.index'));
        $posPage->assertViewHas('cart', fn ($cart) => count($cart['lines']) === 1 && $cart['lines'][0]['quantity'] === 3);
    }

    public function test_cash_pickup_order_is_created_with_pos_channel_and_staff_actor(): void
    {
        $staff = $this->makeStaff();
        $this->addToPosCart($staff, 2);

        $response = $this->actingAs($staff)->postJson(route('dashboard.pos.orders.store'), [
            'phone' => '0241111111',
            'name' => 'Walk-in Ama',
            'fulfilment_type' => 'pickup',
            'payment_method' => 'cash',
        ]);

        $response->assertOk();

        $order = Order::first();

        $this->assertSame('pos', $order->channel);
        $this->assertSame('paid', $order->status);
        $this->assertSame(10000, $order->total);
        $this->assertSame('+233241111111', $order->customer->phone);

        $placedEvent = $order->events()->whereNull('from_status')->first();
        $this->assertSame('staff', $placedEvent->actor_type);
        $this->assertSame($staff->id, $placedEvent->actor_id);
    }

    public function test_cart_is_cleared_after_placing_an_order(): void
    {
        $staff = $this->makeStaff();
        $this->addToPosCart($staff, 1);

        $this->actingAs($staff)->postJson(route('dashboard.pos.orders.store'), [
            'phone' => '0241111111',
            'fulfilment_type' => 'pickup',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($staff)->postJson(route('dashboard.pos.cart.add'), [
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 1,
            'option_ids' => [],
        ]);

        // A fresh add after checkout should start a fresh single-line cart,
        // not append to a leftover line from the order just placed.
        $response->assertJsonCount(1, 'lines');
        $response->assertJsonPath('lines.0.quantity', 1);
    }

    public function test_pos_order_requires_a_phone_number(): void
    {
        $staff = $this->makeStaff();
        $this->addToPosCart($staff, 1);

        $this->actingAs($staff)->postJson(route('dashboard.pos.orders.store'), [
            'fulfilment_type' => 'pickup',
            'payment_method' => 'cash',
        ])->assertJsonValidationErrors('phone');
    }

    public function test_pos_order_rejects_an_empty_cart(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)->postJson(route('dashboard.pos.orders.store'), [
            'phone' => '0241111111',
            'fulfilment_type' => 'pickup',
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_momo_pos_order_is_paid_immediately_like_cash_with_no_paystack_call(): void
    {
        // Momo POS orders never touch Paystack — a fake with no registered
        // stub means the test fails loudly if anything tries to.
        Http::fake();

        $staff = $this->makeStaff();
        $this->addToPosCart($staff, 1);

        $response = $this->actingAs($staff)->postJson(route('dashboard.pos.orders.store'), [
            'phone' => '0241111111',
            'fulfilment_type' => 'pickup',
            'payment_method' => 'momo',
        ]);

        $response->assertOk();
        $response->assertJsonMissingPath('payment_link');

        $order = Order::first();
        $this->assertSame('paid', $order->status);
        $this->assertSame('momo', $order->payment_method);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider' => 'momo', 'amount' => $order->total, 'status' => 'pending',
        ]);
        Http::assertNothingSent();
    }

    public function test_pos_no_longer_accepts_the_old_paystack_payment_method_value(): void
    {
        $staff = $this->makeStaff();
        $this->addToPosCart($staff, 1);

        $this->actingAs($staff)->postJson(route('dashboard.pos.orders.store'), [
            'phone' => '0241111111',
            'fulfilment_type' => 'pickup',
            'payment_method' => 'paystack',
        ])->assertStatus(422);
    }

    public function test_delivery_order_defers_the_fee_since_pos_never_captures_live_location(): void
    {
        DeliveryArea::create(['name' => 'Osu Oxford Street', 'is_active' => true]);

        $staff = $this->makeStaff();
        $this->addToPosCart($staff, 1);

        $area = DeliveryArea::first();

        $response = $this->actingAs($staff)->postJson(route('dashboard.pos.orders.store'), [
            'phone' => '0241111111',
            'fulfilment_type' => 'delivery',
            'area_id' => $area->id,
            'landmark' => 'Near the blue gate',
            'payment_method' => 'cash',
        ]);

        $response->assertOk();

        $order = Order::first();
        $this->assertSame('delivery', $order->fulfilment_type);
        $this->assertSame(0, $order->delivery_fee);
        $this->assertSame('Near the blue gate', $order->delivery_address_snapshot['landmark']);
    }

    public function test_manager_actor_type_is_recorded_correctly(): void
    {
        $manager = User::factory()->create();
        $this->assignRoleAt($manager, 'manager', $this->branch);

        $this->actingAs($manager)->postJson(route('dashboard.pos.cart.add'), [
            'menu_item_id' => $this->shawarma->id,
            'quantity' => 1,
            'option_ids' => [],
        ]);

        $this->actingAs($manager)->postJson(route('dashboard.pos.orders.store'), [
            'phone' => '0241111111',
            'fulfilment_type' => 'pickup',
            'payment_method' => 'cash',
        ]);

        $order = Order::first();
        $placedEvent = $order->events()->whereNull('from_status')->first();
        $this->assertSame('manager', $placedEvent->actor_type);
        $this->assertSame($manager->id, $placedEvent->actor_id);
    }
}
