<?php

namespace App\Contracts;

use App\Models\User;

/**
 * SMS provider is still an open decision (see CLAUDE.md's Open Decisions).
 * Everything that needs to escalate to a human — order acknowledgement
 * escalation today, order confirmations later — depends on this contract
 * instead of a concrete provider, so swapping in a real one later touches
 * one binding, not every call site.
 */
interface Notifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(User $user, string $message, array $context = []): void;
}
