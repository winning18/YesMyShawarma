<?php

namespace Tests\Feature;

use App\Contracts\Notifier;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryArea;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Order;
use App\Services\Customers\CustomerAddressService;
use App\Services\Orders\Data\DeliveryAddressData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone: string, message: string}> */
    private array $sent = [];

    private Customer $customer;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(Notifier::class, function ($mock) {
            $mock->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) {
                $this->sent[] = ['phone' => $phone, 'message' => $message];

                return true;
            });
            $mock->shouldReceive('notify');
        });

        $this->branch = Branch::create([
            'name' => 'Ga Odumase', 'slug' => 'ga-odumase', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '00:00', 'closes_at' => '23:59',
        ]);

        $this->customer = Customer::create(['phone' => '+233241111111', 'name' => 'Ama', 'password' => 'secret-pass']);
    }

    private function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sent)['message'], $match);

        return $match[1];
    }

    private function order(Customer $customer, string $reference = 'ORD-1'): Order
    {
        $order = Order::create([
            'reference' => $reference, 'track_token' => bin2hex(random_bytes(16)), 'customer_id' => $customer->id,
            'branch_id' => $this->branch->id, 'fulfilment_type' => 'pickup', 'subtotal' => 5000, 'total' => 5000,
            'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);
        $order->status = 'delivered';
        $order->placed_at = now();
        $order->save();

        return $order;
    }

    public function test_logged_out_visitors_are_sent_to_the_customer_login(): void
    {
        $this->get(route('customer.account.show'))->assertRedirect(route('customer.login'));
        $this->get(route('customer.account.orders'))->assertRedirect(route('customer.login'));
    }

    public function test_the_header_links_to_my_account(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('home'))->assertSee(route('customer.account.show'));
    }

    public function test_details_and_password_can_be_changed(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->patch(route('customer.account.update'), ['name' => 'Ama Mensah', 'email' => 'ama@example.com'])
            ->assertSessionHas('status');
        $this->assertSame('Ama Mensah', $this->customer->fresh()->name);

        $this->actingAs($this->customer, 'customer')
            ->put(route('customer.account.password'), ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($this->customer, 'customer')
            ->put(route('customer.account.password'), ['current_password' => 'secret-pass', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHas('status');
        $this->assertTrue(Hash::check('new-password-1', $this->customer->fresh()->password));
    }

    public function test_phone_changes_only_after_the_texted_code(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.account.phone.code'), ['phone' => '024-222-2222'])
            ->assertSessionHas('status');
        $this->assertSame('+233242222222', $this->sent[0]['phone']);
        $this->assertSame('+233241111111', $this->customer->fresh()->phone);

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.account.phone.confirm'), ['code' => '000000'])
            ->assertSessionHasErrorsIn('phone', 'code');
        $this->assertSame('+233241111111', $this->customer->fresh()->phone);

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.account.phone.confirm'), ['code' => $this->lastCode()])
            ->assertSessionHas('status');
        $this->assertSame('+233242222222', $this->customer->fresh()->phone);
        $this->assertNotNull($this->customer->fresh()->phone_verified_at);
    }

    public function test_a_number_owned_by_another_account_is_refused_before_any_text(): void
    {
        Customer::create(['phone' => '+233243333333', 'password' => 'theirs-123']);

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.account.phone.code'), ['phone' => '0243333333'])
            ->assertSessionHasErrorsIn('phone', 'phone');

        $this->assertSame([], $this->sent);
    }

    public function test_guest_orders_on_the_new_number_join_the_account(): void
    {
        $guest = Customer::create(['phone' => '+233244444444', 'name' => 'Ama (guest)']);
        $guestOrder = $this->order($guest, 'ORD-GUEST');

        $this->actingAs($this->customer, 'customer')->post(route('customer.account.phone.code'), ['phone' => '0244444444']);
        $this->actingAs($this->customer, 'customer')->post(route('customer.account.phone.confirm'), ['code' => $this->lastCode()]);

        $this->assertSame($this->customer->id, $guestOrder->fresh()->customer_id);
        $this->assertNull($guest->fresh()->phone);
        $this->assertSame('+233244444444', $this->customer->fresh()->phone);
        $event = $guestOrder->events()->latest('id')->first();
        $this->assertSame('customer_merged', $event->meta['action']);
        $this->assertSame($guest->id, $event->meta['from_customer_id']);
    }

    public function test_codes_are_limited_per_number(): void
    {
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->customer, 'customer')->post(route('customer.account.phone.code'), ['phone' => '0245555555'])->assertSessionHas('status');
        }

        $this->actingAs($this->customer, 'customer')->post(route('customer.account.phone.code'), ['phone' => '0245555555'])
            ->assertSessionHasErrorsIn('phone', 'phone');
        $this->assertCount(3, $this->sent);
    }

    public function test_sms_offers_can_be_switched_off_and_on(): void
    {
        $this->actingAs($this->customer, 'customer')->put(route('customer.account.sms-offers'), ['sms_offers' => '0']);
        $this->assertNotNull($this->customer->fresh()->marketing_opted_out_at);

        $this->actingAs($this->customer, 'customer')->put(route('customer.account.sms-offers'), ['sms_offers' => '1']);
        $this->assertNull($this->customer->fresh()->marketing_opted_out_at);
    }

    public function test_forgot_password_by_texted_code(): void
    {
        $this->post(route('customer.password.code'), ['phone' => '0249999999'])->assertSessionHasErrors('phone');
        $this->assertSame([], $this->sent);

        $this->post(route('customer.password.code'), ['phone' => '024-111-1111'])->assertRedirect(route('customer.password.reset'));
        $this->get(route('customer.password.reset'))->assertOk()->assertSee('+233241111111');

        $this->post(route('customer.password.update'), ['code' => '123', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1'])
            ->assertSessionHasErrors('code');

        $this->post(route('customer.password.update'), ['code' => $this->lastCode(), 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1'])
            ->assertRedirect(route('customer.account.show'));

        $this->assertAuthenticatedAs($this->customer, 'customer');
        $this->assertTrue(Hash::check('brand-new-1', $this->customer->fresh()->password));
    }

    public function test_five_wrong_codes_use_the_code_up(): void
    {
        $this->post(route('customer.password.code'), ['phone' => '0241111111']);
        $code = $this->lastCode();

        foreach (range(1, 5) as $i) {
            $this->post(route('customer.password.update'), ['code' => $code === '111111' ? '222222' : '111111', 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1']);
        }

        $this->post(route('customer.password.update'), ['code' => $code, 'password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1'])
            ->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('secret-pass', $this->customer->fresh()->password));
    }

    public function test_addresses_can_be_added_edited_defaulted_and_removed(): void
    {
        $area = DeliveryArea::create(['name' => 'Amasaman', 'is_active' => true]);

        $this->actingAs($this->customer, 'customer')->post(route('customer.account.addresses.store'), [
            'label' => 'Home', 'area_id' => $area->id, 'landmark' => 'Blue gate', 'lat' => 5.70, 'lng' => -0.30,
        ])->assertSessionHas('status');
        $this->actingAs($this->customer, 'customer')->post(route('customer.account.addresses.store'), [
            'area_id' => 'other', 'area_other' => 'Kotoku', 'landmark' => 'Near the school',
        ]);

        [$home, $work] = $this->customer->addresses()->orderBy('id')->get();
        $this->assertTrue($home->is_default);
        $this->assertFalse($work->is_default);
        $this->assertSame('Kotoku', $work->areaName());

        // Editing the text keeps the saved location.
        $this->actingAs($this->customer, 'customer')->put(route('customer.account.addresses.update', $home), [
            'label' => 'Home', 'area_id' => $area->id, 'landmark' => 'Blue gate, 2nd house',
        ]);
        $this->assertSame('Blue gate, 2nd house', $home->fresh()->landmark);
        $this->assertSame('5.7000000', $home->fresh()->lat);

        $this->actingAs($this->customer, 'customer')->post(route('customer.account.addresses.default', $work));
        $this->assertTrue($work->fresh()->is_default);
        $this->assertFalse($home->fresh()->is_default);

        $this->actingAs($this->customer, 'customer')->delete(route('customer.account.addresses.destroy', $work));
        $this->assertTrue($home->fresh()->is_default);
    }

    public function test_someone_elses_address_or_order_is_not_found(): void
    {
        $other = Customer::create(['phone' => '+233246666666', 'password' => 'other-pass']);
        $address = $other->addresses()->create(['landmark' => 'X', 'is_default' => true]);
        $order = $this->order($other, 'ORD-OTHER');

        $this->actingAs($this->customer, 'customer')->delete(route('customer.account.addresses.destroy', $address))->assertNotFound();
        $this->actingAs($this->customer, 'customer')->post(route('customer.account.reorder', $order))->assertNotFound();
        $this->assertNotNull($address->fresh());
    }

    public function test_checkout_can_save_the_address_and_offers_saved_ones(): void
    {
        $service = app(CustomerAddressService::class);
        $delivery = new DeliveryAddressData(areaId: null, ghanapostCode: 'ga-123-4567', landmark: 'Blue gate', lat: 5.7, lng: -0.3, areaOther: 'Kotoku');

        $service->saveFromCheckout($this->customer, $delivery);
        $service->saveFromCheckout($this->customer, new DeliveryAddressData(areaId: null, ghanapostCode: null, landmark: 'Blue gate', lat: 5.71, lng: -0.31, areaOther: 'Kotoku'));

        $this->assertSame(1, $this->customer->addresses()->count(), 'the same place is refreshed, not duplicated');
        $this->assertSame('5.7100000', CustomerAddress::first()->lat);

        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $item = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $this->branch->menuItems()->attach($item->id, ['is_available' => true]);
        DeliveryArea::create(['name' => 'Amasaman', 'is_active' => true]);

        $this->actingAs($this->customer, 'customer')->post(route('cart.add'), ['branch_id' => $this->branch->id, 'menu_item_id' => $item->id, 'quantity' => 1]);
        $saved = $this->actingAs($this->customer, 'customer')->get(route('checkout.show'))->assertOk()->assertSee('Your saved addresses')->viewData('savedAddresses');

        $this->assertSame('Blue gate', $saved[0]['landmark']);
        $this->assertSame(5.71, $saved[0]['lat']);
    }

    public function test_order_again_refills_the_cart_and_names_what_is_gone(): void
    {
        $category = Category::create(['name' => 'Wraps', 'slug' => 'wraps']);
        $shawarma = MenuItem::create(['category_id' => $category->id, 'name' => 'Chicken Shawarma', 'slug' => 'chicken-shawarma', 'base_price' => 5000]);
        $juice = MenuItem::create(['category_id' => $category->id, 'name' => 'Mango Juice', 'slug' => 'mango-juice', 'base_price' => 2000]);
        $group = OptionGroup::create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 3]);
        $cheese = Option::create(['option_group_id' => $group->id, 'name' => 'Cheese', 'price_delta' => 300]);
        $shawarma->optionGroups()->attach($group->id, ['sort_order' => 1]);
        $this->branch->menuItems()->attach($shawarma->id, ['is_available' => true]);
        $this->branch->menuItems()->attach($juice->id, ['is_available' => false]);

        $order = $this->order($this->customer);
        $line = $order->items()->create(['menu_item_id' => $shawarma->id, 'name_snapshot' => 'Chicken Shawarma', 'unit_price_snapshot' => 5000, 'quantity' => 2, 'line_total' => 10600, 'notes' => 'No onions']);
        $line->options()->create(['option_id' => $cheese->id, 'name_snapshot' => 'Cheese', 'price_delta_snapshot' => 300, 'quantity' => 2]);
        $order->items()->create(['menu_item_id' => $juice->id, 'name_snapshot' => 'Mango Juice', 'unit_price_snapshot' => 2000, 'quantity' => 1, 'line_total' => 2000]);
        $order->items()->create(['menu_item_id' => $shawarma->id, 'name_snapshot' => 'Chicken Shawarma', 'unit_price_snapshot' => 0, 'quantity' => 1, 'line_total' => 0, 'is_free' => true]);

        $this->actingAs($this->customer, 'customer')->post(route('customer.account.reorder', $order))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('status', '1 item from your order was added to your cart. Not available at Ga Odumase: Mango Juice.');

        $this->actingAs($this->customer, 'customer')->get(route('cart.show'))
            ->assertSee('Chicken Shawarma')
            ->assertSee('Cart (2)')
            // (5000 × 2) + cheese 300 × 2, fixed for the line.
            ->assertSee('106.00');
    }

    public function test_staff_profile_has_no_self_delete(): void
    {
        $this->assertFalse(Route::has('profile.destroy'));
    }
}
