<?php

namespace App\Services\Notifications;

use App\Contracts\Notifier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder Notifier bound until an SMS provider is chosen and users gain
 * a phone number to send to (neither exists yet — see CLAUDE.md's Open
 * Decisions). Logs instead of silently doing nothing, so escalation is
 * visible in logs even before real delivery exists.
 */
class LogNotifier implements Notifier
{
    public function notify(User $user, string $message, array $context = []): void
    {
        Log::info("[Notifier stub] {$user->name} (#{$user->id}): {$message}", $context);
    }
}
