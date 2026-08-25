<?php

namespace Tests\Unit;

use App\Services\Notifications\ArkeselNotifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArkeselNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.arkesel.api_key' => 'test-api-key',
            'services.arkesel.sender_id' => 'YesShawarma',
            'services.arkesel.base_url' => 'https://sms.arkesel.com/api/v2/sms/send',
        ]);
    }

    public function test_sends_the_expected_request_shape(): void
    {
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'success'], 200)]);

        (new ArkeselNotifier)->notify('+233241234567', 'Your order is ready.');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.arkesel.com/api/v2/sms/send'
                && $request->hasHeader('api-key', 'test-api-key')
                && $request['sender'] === 'YesShawarma'
                && $request['message'] === 'Your order is ready.'
                // No leading "+" — Arkesel expects "233XXXXXXXXX".
                && $request['recipients'] === ['233241234567'];
        });
    }

    public function test_a_failed_response_does_not_throw(): void
    {
        Http::fake(['sms.arkesel.com/*' => Http::response(['message' => 'Invalid sender'], 422)]);

        // No exception means the order/escalation flow this is attached to
        // was never at risk — same guarantee SafeBroadcast gives broadcasts.
        (new ArkeselNotifier)->notify('+233241234567', 'Your order is ready.');

        $this->assertTrue(true);
    }

    public function test_a_connection_exception_does_not_throw(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Could not connect');
        });

        (new ArkeselNotifier)->notify('+233241234567', 'Your order is ready.');

        $this->assertTrue(true);
    }
}
