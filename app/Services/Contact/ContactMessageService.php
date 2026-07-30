<?php

namespace App\Services\Contact;

use App\Mail\ContactMessageSubmitted;
use Illuminate\Support\Facades\Mail;

class ContactMessageService
{
    /**
     * Business inboxes that receive every contact form submission. Mail is
     * unconfigured in this environment (MAIL_MAILER=log) — same deferred
     * pattern as the SMS Notifier contract, real sending starts working the
     * moment SMTP credentials are set without any code change here.
     *
     * @var list<string>
     */
    private const RECIPIENTS = [
        'yesmyshawarma@gmail.com',
        'yesmygrill@gmail.com',
    ];

    public function send(string $name, ?string $email, ?string $phone, string $message): void
    {
        Mail::to(self::RECIPIENTS)->send(
            new ContactMessageSubmitted($name, $email, $phone, $message)
        );
    }
}
