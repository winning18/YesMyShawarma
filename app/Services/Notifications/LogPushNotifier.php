<?php

namespace App\Services\Notifications;

use App\Contracts\PushNotifier;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fallback PushNotifier bound whenever VAPID keys aren't configured
 * (local/testing — see AppServiceProvider, same reasoning as LogNotifier
 * for SMS). Logs instead of silently doing nothing, so a missing
 * environment setup is visible rather than a mysteriously quiet feature.
 */
class LogPushNotifier implements PushNotifier
{
    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $data
     */
    public function notify(Collection $subscriptions, string $title, string $body, array $data = []): void
    {
        Log::info("[PushNotifier stub] {$title}: {$body}", [
            'subscription_ids' => $subscriptions->pluck('id')->all(),
            'data' => $data,
        ]);
    }
}
