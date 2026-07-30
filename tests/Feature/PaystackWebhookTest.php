<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaystackWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paystack.secret_key' => 'test_secret_key']);

        Bus::fake();
    }

    private function signatureFor(array $payload): string
    {
        return hash_hmac('sha512', json_encode($payload), 'test_secret_key');
    }

    public function test_valid_signature_dispatches_the_processing_job_and_responds_ok(): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'REF123', 'amount' => 7000]];

        $response = $this->postJson('/api/webhooks/paystack', $payload, [
            'x-paystack-signature' => $this->signatureFor($payload),
        ]);

        $response->assertOk();
        Bus::assertDispatched(ProcessPaystackWebhook::class);
    }

    public function test_invalid_signature_is_rejected_and_nothing_is_dispatched(): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'REF123', 'amount' => 7000]];

        $response = $this->postJson('/api/webhooks/paystack', $payload, [
            'x-paystack-signature' => 'not-the-right-signature',
        ]);

        $response->assertStatus(400);
        Bus::assertNotDispatched(ProcessPaystackWebhook::class);
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'REF123', 'amount' => 7000]];

        $response = $this->postJson('/api/webhooks/paystack', $payload);

        $response->assertStatus(400);
        Bus::assertNotDispatched(ProcessPaystackWebhook::class);
    }
}
