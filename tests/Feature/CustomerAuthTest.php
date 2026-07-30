<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_is_logged_in(): void
    {
        $response = $this->post(route('customer.register'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated('customer');

        $this->assertDatabaseHas('customers', ['phone' => '+233241111111', 'name' => 'Ama']);
    }

    public function test_registering_sets_password_on_an_existing_guest_row_rather_than_duplicating(): void
    {
        $existing = Customer::create(['phone' => '+233241111111']);

        $this->post(route('customer.register'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseCount('customers', 1);
        $this->assertNotNull($existing->fresh()->password);
    }

    public function test_registering_an_already_registered_phone_is_rejected(): void
    {
        Customer::create(['phone' => '+233241111111', 'password' => 'existing-password']);

        $this->post(route('customer.register'), [
            'name' => 'Ama',
            'phone' => '0241111111',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest('customer');
    }

    public function test_login_with_correct_credentials(): void
    {
        Customer::create(['phone' => '+233241111111', 'password' => 'secret123']);

        $this->post(route('customer.login'), [
            'phone' => '0241111111',
            'password' => 'secret123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated('customer');
    }

    public function test_login_with_wrong_password_fails(): void
    {
        Customer::create(['phone' => '+233241111111', 'password' => 'secret123']);

        $this->post(route('customer.login'), [
            'phone' => '0241111111',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest('customer');
    }

    public function test_logged_in_customer_visiting_login_is_sent_home_not_to_staff_dashboard(): void
    {
        $customer = Customer::create(['phone' => '+233241111111', 'password' => 'secret123']);

        $response = $this->actingAs($customer, 'customer')->get(route('customer.login'));

        $response->assertRedirect(route('home'));
    }

    public function test_logout(): void
    {
        $customer = Customer::create(['phone' => '+233241111111', 'password' => 'secret123']);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.logout'))
            ->assertRedirect('/');

        $this->assertGuest('customer');
    }

    public function test_staff_login_route_is_unaffected_by_customer_auth_routes(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }
}
