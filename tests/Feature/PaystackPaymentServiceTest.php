<?php

namespace Tests\Feature;

use App\Exceptions\PaymentException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
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

        Http::assertSent(fn ($request) => str_ends_with($request['email'], '@guests.yesmyshawarma.invalid'));
    }

    public function test_rejects_a_cash_order(): void
    {
        $customer = Customer::create(['phone' => '+233241113333']);
        $order = $this->makeOrder($customer);
        $order->update(['payment_method' => 'cash']);

        $this->expectException(PaymentException::class);

        $this->service->initializeForOrder($order, 'https://example.test/callback');
    }
}
