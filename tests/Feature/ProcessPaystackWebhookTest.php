<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaystackWebhook;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Payments\PaystackPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessPaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'Osu', 'slug' => 'osu', 'phone' => '+233200000001', 'address' => 'A',
            'lat' => 5.5, 'lng' => -0.1, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]);

        $customer = Customer::create(['phone' => '+233241111111']);

        $this->order = Order::create([
            'reference' => 'ORD-'.uniqid(),
            'track_token' => bin2hex(random_bytes(16)),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'fulfilment_type' => 'pickup',
            'subtotal' => 7000,
            'total' => 7000,
            'payment_method' => 'paystack',
            'payment_status' => 'pending',
        ]);
        $this->order->status = 'pending_payment';
        $this->order->save();

        $this->order->payments()->create([
            'provider' => 'paystack',
            'provider_reference' => 'REF123',
            'amount' => 7000,
            'currency' => 'GHS',
            'status' => 'pending',
        ]);
    }

    private function handleWebhook(array $payload): void
    {
        (new ProcessPaystackWebhook($payload))->handle(app(PaystackPaymentService::class));
    }

    public function test_successful_charge_transitions_order_to_paid(): void
    {
        $this->handleWebhook(['event' => 'charge.success', 'data' => ['reference' => 'REF123', 'amount' => 7000]]);

        $this->order->refresh();
        $this->assertSame('paid', $this->order->status);
        $this->assertSame('paid', $this->order->payment_status);

        $this->assertDatabaseHas('payments', [
            'provider_reference' => 'REF123', 'status' => 'paid',
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $this->order->id, 'from_status' => 'pending_payment', 'to_status' => 'paid', 'actor_type' => 'system',
        ]);
    }

    public function test_duplicate_delivery_for_an_already_paid_payment_is_a_no_op(): void
    {
        $this->handleWebhook(['event' => 'charge.success', 'data' => ['reference' => 'REF123', 'amount' => 7000]]);
        $this->handleWebhook(['event' => 'charge.success', 'data' => ['reference' => 'REF123', 'amount' => 7000]]);

        $this->assertDatabaseCount('order_events', 1);
    }

    public function test_amount_mismatch_leaves_order_pending_and_flags_the_payment(): void
    {
        $this->handleWebhook(['event' => 'charge.success', 'data' => ['reference' => 'REF123', 'amount' => 100]]);

        $this->order->refresh();
        $this->assertSame('pending_payment', $this->order->status);

        $this->assertDatabaseHas('payments', [
            'provider_reference' => 'REF123', 'status' => 'amount_mismatch',
        ]);
        $this->assertDatabaseCount('order_events', 0);
    }

    public function test_unknown_reference_does_not_crash(): void
    {
        $this->handleWebhook(['event' => 'charge.success', 'data' => ['reference' => 'DOES-NOT-EXIST', 'amount' => 7000]]);

        $this->order->refresh();
        $this->assertSame('pending_payment', $this->order->status);
    }

    public function test_non_charge_success_events_are_ignored(): void
    {
        $this->handleWebhook(['event' => 'charge.failed', 'data' => ['reference' => 'REF123', 'amount' => 7000]]);

        $this->order->refresh();
        $this->assertSame('pending_payment', $this->order->status);
        $this->assertDatabaseHas('payments', ['provider_reference' => 'REF123', 'status' => 'pending']);
    }
}
