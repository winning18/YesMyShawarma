<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterSubscriber;

class NewsletterService
{
    /**
     * Idempotent on purpose — resubscribing with the same email is a normal,
     * expected action from a visitor who forgot they already signed up, not
     * an error condition worth surfacing as one.
     */
    public function subscribe(string $email): void
    {
        NewsletterSubscriber::firstOrCreate(
            ['email' => $email],
            ['subscribed_at' => now()],
        );
    }
}
