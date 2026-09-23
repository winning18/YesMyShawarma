<?php

namespace App\Contracts;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;

/**
 * Web Push (browser notifications) — mirrors Notifier's shape (a contract
 * over a concrete provider), but takes subscriptions rather than a phone
 * number, since that's what identifies a recipient here. See
 * .claude/rules/realtime.md's "Web Push" section.
 */
interface PushNotifier
{
    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $data
     */
    public function notify(Collection $subscriptions, string $title, string $body, array $data = []): void;
}
