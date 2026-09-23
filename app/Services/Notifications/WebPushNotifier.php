<?php

namespace App\Services\Notifications;

use App\Contracts\PushNotifier;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Real Web Push delivery (bound in AppServiceProvider once VAPID_PUBLIC_KEY/
 * VAPID_PRIVATE_KEY are configured — LogPushNotifier otherwise).
 *
 * A push failure must never take down whatever triggered it (an order
 * placement) — same reasoning as ArkeselNotifier and SafeBroadcast: the
 * thing this is attached to has already succeeded by the time this runs.
 */
class WebPushNotifier implements PushNotifier
{
    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $data
     */
    public function notify(Collection $subscriptions, string $title, string $body, array $data = []): void
    {
        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ],
            ]);

            $payload = json_encode(['title' => $title, 'body' => $body, 'data' => $data]);
            $byEndpoint = $subscriptions->keyBy('endpoint');

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    new Subscription(
                        $subscription->endpoint,
                        $subscription->public_key,
                        $subscription->auth_token,
                        ContentEncoding::aes128gcm,
                    ),
                    $payload,
                );
            }

            // A 404/410 from the push service means the browser itself
            // dropped the subscription (uninstalled, cleared site data,
            // expired) — nothing will ever revive it, so the row is
            // deleted rather than retried forever on every future order.
            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                    $byEndpoint->get($report->getEndpoint())?->delete();
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
