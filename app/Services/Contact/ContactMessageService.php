<?php

namespace App\Services\Contact;

use App\Mail\ContactMessageSubmitted;
use App\Services\Settings\BusinessProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactMessageService
{
    public function __construct(private readonly BusinessProfile $profile) {}

    /**
     * To the inboxes on the Business profile ("Contact form sends to").
     * With none set the message is only logged, so a new install never
     * mails a stranger.
     */
    public function send(string $name, ?string $email, ?string $phone, string $message): void
    {
        $recipients = $this->profile->contactRecipients();

        if ($recipients === []) {
            Log::warning('Contact form message not emailed: no recipients on the Business profile', ['name' => $name]);

            return;
        }

        Mail::to($recipients)->send(
            new ContactMessageSubmitted($name, $email, $phone, $message)
        );
    }
}
