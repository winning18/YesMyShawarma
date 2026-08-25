<?php

namespace App\Services\Notifications;

use App\Contracts\Notifier;
use Illuminate\Support\Facades\Log;

/**
 * Fallback Notifier bound whenever Arkesel isn't configured (local/testing —
 * see AppServiceProvider). Logs instead of silently doing nothing, so
 * escalation and order notifications stay visible without needing real
 * credentials on hand.
 */
class LogNotifier implements Notifier
{
    public function notify(string $phone, string $message, array $context = []): void
    {
        Log::info("[Notifier stub] {$phone}: {$message}", $context);
    }
}
