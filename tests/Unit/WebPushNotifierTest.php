<?php

namespace Tests\Unit;

use App\Models\PushSubscription;
use App\Services\Notifications\WebPushNotifier;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Unlike ArkeselNotifier, this can't assert against Http::fake() — the
 * underlying minishlink/web-push library talks to each push service via
 * its own PSR-18 client, not Laravel's Http facade. What's actually worth
 * locking in here is the one guarantee this class exists to make: nothing
 * it does is allowed to throw back into the order flow it's attached to,
 * even when the input is malformed (a realistic failure mode — a
 * corrupted or stale row) rather than just a network hiccup.
 */
class WebPushNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vapid.public_key' => 'BDdB8an5gs13Ev6lFvEFGzo8YsIlWdD-n-xmhomj5ezRBL8qBERQZnIETyAXeRl4NvW8gtMtEx9IJgBLdnAXiDI',
            'services.vapid.private_key' => 'SBoZ9xxbzMRgSgHI6xM3uZyHWVwqfPZjw4PSAO68X-k',
            'services.vapid.subject' => 'mailto:info@yesmyshawarma.com',
        ]);
    }

    public function test_an_empty_subscription_list_is_a_no_op(): void
    {
        (new WebPushNotifier)->notify(new Collection, 'Title', 'Body');

        $this->assertTrue(true);
    }

    public function test_a_malformed_subscription_never_throws(): void
    {
        $subscription = new PushSubscription([
            'user_id' => 1,
            'endpoint' => 'not-a-real-endpoint',
            'public_key' => 'not-valid-base64-key!!',
            'auth_token' => 'also-not-valid!!',
        ]);

        (new WebPushNotifier)->notify(collect([$subscription]), 'Title', 'Body');

        $this->assertTrue(true);
    }
}
