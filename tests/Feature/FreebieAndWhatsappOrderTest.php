<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Reports\OrderReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FreebieAndWhatsappOrderTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $shawarma;

    private MenuItem $coke;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);

        $wraps = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $drinks = Category::create(['name' => 'Drinks', 'slug' => 'drinks', 'is_beverage' => true]);
        $this->shawarma = MenuItem::create(['category_id' => $wraps->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $this->coke = MenuItem::create(['category_id' => $drinks->id, 'name' => 'Coke', 'slug' => 'coke', 'base_price' => 800]);
        $this->branch->menuItems()->attach([$this->shawarma->id, $this->coke->id], ['is_available' => true]);
    }

    private function userWithRole(string $role, string $name = 'Someone'): User
    {
        $user = User::factory()->create(['name' => $name]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $this->branch->id]);
    }

    private function addToCart(User $user, MenuItem $item, int $quantity = 1): void
    {
        $this->as($user)->postJson(route('dashboard.pos.cart.add'), ['menu_item_id' => $item->id, 'quantity' => $quantity, 'option_ids' => []])->assertOk();
    }

    private function place(User $user, array $fields)
    {
        return $this->as($user)->postJson(route('dashboard.pos.orders.store'), ['fulfilment_type' => 'pickup', ...$fields]);
    }

    private function freebie(User $staff, array $overrides = []): Order
    {
        $this->addToCart($staff, $this->shawarma, 2);
        $this->place($staff, [
            'payment_method' => 'complimentary', 'name' => 'Ama',
            'freebie_recipient_type' => 'customer', 'freebie_reason' => 'Birthday', ...$overrides,
        ])->assertOk();

        return Order::with('complimentary')->latest('id')->firstOrFail();
    }

    private function wednesdayPromo(): void
    {
        Promotion::create([
            'code' => 'WED', 'type' => 'buy_x_get_y_free', 'value' => 0, 'buy_quantity' => 2, 'free_quantity' => 1,
            'free_menu_item_id' => $this->shawarma->id,
            'is_automatic' => true, 'recurring_days' => [now('Africa/Accra')->dayOfWeek], 'is_active' => true,
        ]);
    }

    public function test_a_freebie_costs_the_customer_nothing_and_is_never_sales(): void
    {
        $staff = $this->userWithRole('staff', 'Kofi');
        $order = $this->freebie($staff);

        $this->assertSame(['complimentary', 10000, 10000, 0, 'paid'], [$order->payment_method, $order->subtotal, $order->discount_total, $order->total, $order->status]);
        $this->assertStringStartsWith('FREE-', $order->reference);
        $this->assertSame(['customer', 'Ama', 'Birthday', 'pending', 'staff'], [
            $order->complimentary->recipient_type, $order->complimentary->recipient_name, $order->complimentary->reason,
            $order->complimentary->status, $order->complimentary->recorder_role,
        ]);

        $sales = app(OrderReportService::class)->financialSummary(now()->subDay(), now()->addDay(), ignoreBranchScope: true);
        $this->assertSame(0, $sales['revenue_total']);
    }

    public function test_a_freebie_needs_who_and_why(): void
    {
        $staff = $this->userWithRole('staff');
        $this->addToCart($staff, $this->shawarma);

        $this->place($staff, ['payment_method' => 'complimentary', 'freebie_recipient_type' => 'customer'])
            ->assertUnprocessable()->assertJsonValidationErrors(['freebie_reason', 'freebie_recipient_name']);
        $this->place($staff, ['payment_method' => 'complimentary', 'freebie_recipient_type' => 'staff', 'freebie_reason' => 'Staff meal'])
            ->assertUnprocessable()->assertJsonValidationErrors(['freebie_recipient_user_id']);
        $this->place($staff, ['payment_method' => 'complimentary', 'fulfilment_type' => 'delivery', 'name' => 'Ama', 'freebie_recipient_type' => 'customer', 'freebie_reason' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fulfilment_type']);
    }

    public function test_a_staff_meal_freebie_names_the_staff_member(): void
    {
        $staff = $this->userWithRole('staff');
        $cook = $this->userWithRole('staff', 'Esi the cook');

        $order = $this->freebie($staff, ['name' => null, 'freebie_recipient_type' => 'staff', 'freebie_recipient_user_id' => $cook->id, 'freebie_reason' => 'Staff meal']);

        $this->assertSame($cook->id, $order->complimentary->recipient_user_id);
        $this->assertSame('Esi the cook', $order->complimentary->recipientLabel());
    }

    public function test_no_promotion_on_a_freebie(): void
    {
        $this->wednesdayPromo();
        $order = $this->freebie($this->userWithRole('staff'));

        $this->assertNull($order->promotion_id);
        $this->assertSame([false], $order->items()->pluck('is_free')->map(fn ($v) => (bool) $v)->all());
    }

    public function test_a_manager_approves_or_rejects_a_staff_freebie(): void
    {
        $staff = $this->userWithRole('staff', 'Kofi');
        $first = $this->freebie($staff)->complimentary;
        $second = $this->freebie($staff)->complimentary;
        $manager = $this->userWithRole('manager');

        $this->as($manager)->get(route('dashboard.freebies.index'))->assertOk()->assertSee('Birthday')
            ->assertSee('title="Waiting for your approval">2</span>', false);

        $this->as($manager)->post(route('dashboard.freebies.approve', $first))->assertSessionHas('status');
        $this->as($manager)->post(route('dashboard.freebies.reject', $second), [])->assertSessionHasErrors('reason');
        $this->as($manager)->post(route('dashboard.freebies.reject', $second), ['reason' => 'Not authorised'])->assertSessionHas('status');

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame(['rejected', 'Not authorised'], [$second->fresh()->status, $second->fresh()->review_note]);
        $this->assertSame('freebie_rejected', $second->order->events()->latest('id')->first()->meta['action']);

        // Rejected stays out of sales; the page totals it as a loss to follow up.
        $summary = $this->as($manager)->get(route('dashboard.freebies.index'))->viewData('summary');
        $this->assertSame(['count' => 1, 'value' => 10000], $summary['rejected']);
        $this->assertSame([['label' => 'Kofi', 'count' => 2, 'value' => 20000]], $summary['by_giver']);
    }

    public function test_approval_follows_the_market_list_tiers(): void
    {
        $manager = $this->userWithRole('manager');
        $managerFreebie = $this->freebie($manager)->complimentary;
        $otherManager = $this->userWithRole('manager');

        $this->as($manager)->post(route('dashboard.freebies.approve', $managerFreebie))->assertForbidden();
        $this->as($otherManager)->post(route('dashboard.freebies.approve', $managerFreebie))->assertForbidden();
        $this->as($this->userWithRole('owner'))->post(route('dashboard.freebies.approve', $managerFreebie))->assertSessionHas('status');

        $ownerFreebie = $this->freebie($this->userWithRole('owner'))->complimentary;
        $this->assertSame('approved', $ownerFreebie->status);

        $this->as($this->userWithRole('staff'))->get(route('dashboard.freebies.index'))->assertForbidden();
    }

    public function test_a_whatsapp_order_gets_ten_cedis_off_the_food_only(): void
    {
        $staff = $this->userWithRole('staff');
        $this->addToCart($staff, $this->shawarma);
        $this->addToCart($staff, $this->coke);

        $this->place($staff, ['payment_method' => 'cash', 'source' => 'whatsapp'])->assertOk();

        $order = Order::latest('id')->first();
        $this->assertSame(['whatsapp', 5800, 1000, 4800], [$order->source, $order->subtotal, $order->discount_total, $order->total]);
        $this->assertStringStartsWith('WA-', $order->reference);
    }

    public function test_the_reward_never_exceeds_the_food_and_drinks_alone_get_nothing(): void
    {
        $staff = $this->userWithRole('staff');
        $this->addToCart($staff, $this->coke, 2);
        $this->place($staff, ['payment_method' => 'cash', 'source' => 'whatsapp'])->assertOk();
        $this->assertSame(0, Order::latest('id')->first()->discount_total);

        $cheap = MenuItem::create(['category_id' => $this->shawarma->category_id, 'name' => 'Fries', 'slug' => 'fries', 'base_price' => 600]);
        $this->branch->menuItems()->attach($cheap->id, ['is_available' => true]);
        $this->addToCart($staff, $cheap);
        $this->place($staff, ['payment_method' => 'momo', 'source' => 'whatsapp'])->assertOk();
        $this->assertSame([600, 0], [Order::latest('id')->first()->discount_total, Order::latest('id')->first()->total]);
    }

    public function test_whatsapp_orders_are_pickup_and_cash_or_momo_only(): void
    {
        $staff = $this->userWithRole('staff');
        $this->addToCart($staff, $this->shawarma);

        $this->place($staff, ['payment_method' => 'cash', 'source' => 'whatsapp', 'fulfilment_type' => 'delivery'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fulfilment_type']);
        $this->place($staff, ['payment_method' => 'bolt_food', 'source' => 'whatsapp'])
            ->assertUnprocessable()->assertJsonValidationErrors(['source']);
    }

    public function test_on_a_promotion_day_the_promotion_wins_when_earned(): void
    {
        $this->wednesdayPromo();
        $staff = $this->userWithRole('staff');

        // Earns the free shawarma: no GH₵10.
        $this->addToCart($staff, $this->shawarma, 2);
        $this->place($staff, ['payment_method' => 'cash', 'source' => 'whatsapp'])->assertOk();
        $earned = Order::latest('id')->first();
        $this->assertSame(0, $earned->discount_total);
        $this->assertNotNull($earned->promotion_id);
        $this->assertTrue($earned->items()->where('is_free', true)->exists());

        // Doesn't earn it: still gets the GH₵10.
        $this->addToCart($staff, $this->shawarma, 1);
        $this->place($staff, ['payment_method' => 'cash', 'source' => 'whatsapp'])->assertOk();
        $notEarned = Order::latest('id')->first();
        $this->assertSame(1000, $notEarned->discount_total);
        $this->assertNull($notEarned->promotion_id);
    }

    public function test_the_page_totals_whatsapp_rewards(): void
    {
        $staff = $this->userWithRole('staff');
        $this->addToCart($staff, $this->shawarma);
        $this->place($staff, ['payment_method' => 'cash', 'source' => 'whatsapp'])->assertOk();

        $whatsapp = $this->as($this->userWithRole('manager'))->get(route('dashboard.freebies.index'))->viewData('whatsapp');
        $this->assertSame(['count' => 1, 'rewards' => 1000, 'sales' => 4000], $whatsapp);
    }
}
