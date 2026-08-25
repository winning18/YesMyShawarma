<?php

namespace Tests\Feature;

use App\Exceptions\PaymentException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Payments\PaystackClient;
use App\Services\Payments\PaystackPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaystackPaymentService $service;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaystackPaymentService::class);

        $this->branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);
    }

    private function makeOrder(Customer $customer): Order
    {
        $order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 7000,
            'total' => 7000,
            'payment_method' => 'paystack',
            'payment_status' => 'pending',
        ]);
        $order->status = 'pending_payment';
        $order->save();

        return $order;
    }

    public function test_initializes_transaction_and_records_a_pending_payment(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'abc123',
                    'reference' => 'whatever-paystack-echoes',
                ],
            ], 200),
        ]);

        $customer = Customer::create(['phone' => '+233241111111', 'email' => 'ama@example.com']);
        $order = $this->makeOrder($customer);

        $result = $this->service->initializeForOrder($order, 'https://example.test/callback');

        $this->assertSame('https://checkout.paystack.com/abc123', $result['authorization_url']);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'paystack',
            'provider_reference' => $result['reference'],
            'amount' => 7000,
            'status' => 'pending',
        ]);

        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request['email'] === 'ama@example.com'
                && $request['amount'] === $order->total
                && $request['currency'] === 'GHS';
        });
    }

    public function test_uses_a_placeholder_email_when_customer_has_none(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz', 'reference' => 'x'],
            ], 200),
        ]);

        $customer = Customer::create(['phone' => '+233241112222']);
        $order = $this->makeOrder($customer);

        $this->service->initializeForOrder($order, 'https://example.test/callback');

        Http::assertSent(fn ($request) => str_ends_with($request['email'], '@guests.yesmyshawarma.com'));
    }

    public function test_rejects_a_cash_order(): void
    {
        $customer = Customer::create(['phone' => '+233241113333']);
        $order = $this->makeOrder($customer);
        $order->update(['payment_method' => 'cash']);

        $this->expectException(PaymentException::class);

        $this->service->initializeForOrder($order, 'https://example.test/callback');
    }

    public function test_rejects_a_momo_order(): void
    {
        // POS's momo is an in-house manual payment (Order::MANUALLY_
        // SETTLED_PAYMENT_METHODS) — it must never reach Paystack.
        Http::fake();

        $customer = Customer::create(['phone' => '+233241114444']);
        $order = $this->makeOrder($customer);
        $order->update(['payment_method' => 'momo']);

        $this->expectException(PaymentException::class);

        $this->service->initializeForOrder($order, 'https://example.test/callback');
    }

    public function test_verify_transaction_calls_the_expected_endpoint(): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 7000, 'reference' => 'REF999'],
            ], 200),
        ]);

        $result = app(PaystackClient::class)->verifyTransaction('REF999');

        $this->assertSame('success', $result['data']['status']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/transaction/verify/REF999'
            && $request->method() === 'GET'
        );
    }

    public function test_confirm_payment_transitions_the_order_to_paid(): void
    {
        $customer = Customer::create(['phone' => '+233241115555']);
        $order = $this->makeOrder($customer);
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'REF-CONFIRM',
            'amount' => 7000, 'currency' => 'GHS', 'status' => 'pending',
        ]);

        $this->service->confirmPayment('REF-CONFIRM', 7000, ['event' => 'charge.success']);

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertDatabaseHas('payments', ['provider_reference' => 'REF-CONFIRM', 'status' => 'paid']);
    }

    public function test_confirm_payment_is_idempotent_when_called_twice(): void
    {
        // The actual race-safety claim this whole feature rests on: the
        // webhook and the return-from-Paystack verify path both call this
        // exact method — whichever gets here first must do the real work,
        // the other must be a provable no-op, not a second transition.
        $customer = Customer::create(['phone' => '+233241116666']);
        $order = $this->makeOrder($customer);
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'REF-TWICE',
            'amount' => 7000, 'currency' => 'GHS', 'status' => 'pending',
        ]);

        $this->service->confirmPayment('REF-TWICE', 7000, ['source' => 'first']);
        $this->service->confirmPayment('REF-TWICE', 7000, ['source' => 'second']);

        $this->assertDatabaseCount('order_events', 1);
        $this->assertDatabaseHas('payments', ['provider_reference' => 'REF-TWICE', 'status' => 'paid']);
    }

    public function test_confirm_payment_flags_an_amount_mismatch_without_transitioning(): void
    {
        $customer = Customer::create(['phone' => '+233241117777']);
        $order = $this->makeOrder($customer);
        $order->payments()->create([
            'provider' => 'paystack', 'provider_reference' => 'REF-MISMATCH',
            'amount' => 7000, 'currency' => 'GHS', 'status' => 'pending',
        ]);

        $this->service->confirmPayment('REF-MISMATCH', 100, ['event' => 'charge.success']);

        $order->refresh();
        $this->assertSame('pending_payment', $order->status);
        $this->assertDatabaseHas('payments', ['provider_reference' => 'REF-MISMATCH', 'status' => 'amount_mismatch']);
    }

    public function test_confirm_payment_for_an_unknown_reference_does_not_crash(): void
    {
        $this->service->confirmPayment('DOES-NOT-EXIST', 7000, ['event' => 'charge.success']);

        $this->assertTrue(true);
    }
}
