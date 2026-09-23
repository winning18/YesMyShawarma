<?php

namespace Tests\Feature;

use App\Contracts\PushNotifier;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Orders\Data\PlaceOrderData;
use App\Services\Orders\Data\PlaceOrderItemData;
use App\Services\Orders\OrderCreationService;
use App\Services\Orders\OrderStateMachine;
use App\Services\Orders\OrderTransferService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NewOrderPushNotifierTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5560, 'lng' => -0.1969, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $this->menuItem = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 3500,
        ]);
        $this->branch->menuItems()->attach($this->menuItem->id, ['is_available' => true]);
    }

    private function assignRoleAt(User $user, string $role, Branch $branch): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function makeSubscribedUser(string $role): User
    {
        $user = $this->assignRoleAt(User::factory()->create(), $role, $this->branch);

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.uniqid(),
            'public_key' => 'p256dh-'.uniqid(),
            'auth_token' => 'auth-'.uniqid(),
        ]);

        return $user;
    }

    public function test_placing_a_cash_order_pushes_to_subscribed_staff_at_that_branch(): void
    {
        $staff = $this->makeSubscribedUser('staff');

        $this->mock(PushNotifier::class, function ($mock) use ($staff) {
            $mock->shouldReceive('notify')->once()
                ->withArgs(fn ($subscriptions, $title, $body, $data) => $subscriptions->pluck('user_id')->contains($staff->id)
                    && str_contains($title, 'New order')
                    && $data['url'] === route('dashboard')
                );
        });

        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->menuItem->id, quantity: 1, optionQuantities: [])],
        ));
    }

    public function test_a_still_pending_paystack_order_does_not_push_yet(): void
    {
        Http::fake([
            'api.paystack.co/*' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://x', 'reference' => 'r']], 200),
        ]);
        $this->makeSubscribedUser('staff');

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'paystack',
            items: [new PlaceOrderItemData(menuItemId: $this->menuItem->id, quantity: 1, optionQuantities: [])],
        ));
    }

    public function test_a_paystack_order_pushes_once_the_webhook_confirms_it_paid(): void
    {
        $staff = $this->makeSubscribedUser('staff');

        $order = app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'paystack',
            items: [new PlaceOrderItemData(menuItemId: $this->menuItem->id, quantity: 1, optionQuantities: [])],
        ));

        $this->assertSame('pending_payment', $order->status);

        $this->mock(PushNotifier::class, function ($mock) use ($staff) {
            $mock->shouldReceive('notify')->once()
                ->withArgs(fn ($subscriptions) => $subscriptions->pluck('user_id')->contains($staff->id));
        });

        app(OrderStateMachine::class)->transition($order, 'paid', 'system');
    }

    public function test_manager_and_general_manager_are_pushed_to_the_live_board_route_not_the_staff_one(): void
    {
        $manager = $this->makeSubscribedUser('manager');

        $this->mock(PushNotifier::class, function ($mock) use ($manager) {
            $mock->shouldReceive('notify')->once()
                ->withArgs(fn ($subscriptions, $title, $body, $data) => $subscriptions->pluck('user_id')->contains($manager->id)
                    && $data['url'] === route('dashboard.orders.live')
                );
        });

        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->menuItem->id, quantity: 1, optionQuantities: [])],
        ));
    }

    public function test_owner_is_never_pushed_a_new_order_alert(): void
    {
        $this->makeSubscribedUser('owner');

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->menuItem->id, quantity: 1, optionQuantities: [])],
        ));
    }

    public function test_transferring_a_still_unaccepted_order_pushes_the_destination_branchs_staff(): void
    {
        $destination = Branch::create([
            'name' => 'Pokuase', 'slug' => 'pokuase', 'phone' => '+233200000002', 'address' => 'B',
            'lat' => 5.7, 'lng' => -0.3, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ])->fresh(); // is_active/is_accepting_orders are DB-level defaults,
        // never reflected on the in-memory model create() itself returns —
        // OrderTransferService::transfer() is called directly below rather
        // than through the controller's own Branch::findOrFail() re-fetch.
        $destination->menuItems()->attach($this->menuItem->id, ['is_available' => true]);
        $destinationStaff = $this->assignRoleAt(User::factory()->create(), 'staff', $destination);
        PushSubscription::create([
            'user_id' => $destinationStaff->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.uniqid(),
            'public_key' => 'p256dh-'.uniqid(),
            'auth_token' => 'auth-'.uniqid(),
        ]);

        $order = app(OrderCreationService::class)->create(new PlaceOrderData(
            customerPhone: '+233241111111',
            customerName: 'Ama',
            branchId: $this->branch->id,
            fulfilmentType: 'pickup',
            paymentMethod: 'cash',
            items: [new PlaceOrderItemData(menuItemId: $this->menuItem->id, quantity: 1, optionQuantities: [])],
        ));

        $mover = $this->assignRoleAt(User::factory()->create(), 'manager', $this->branch);

        $this->mock(PushNotifier::class, function ($mock) use ($destinationStaff) {
            $mock->shouldReceive('notify')->once()
                ->withArgs(fn ($subscriptions) => $subscriptions->pluck('user_id')->contains($destinationStaff->id));
        });

        app(OrderTransferService::class)->transfer(
            $order->load('items'), $destination, $mover, 'manager', null, null, true,
        );
    }
}
