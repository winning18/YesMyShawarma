<?php

namespace App\Services\Contact;

use App\Mail\ContactMessageSubmitted;
use Illuminate\Support\Facades\Mail;

class ContactMessageService
{
    /**
     * Business inboxes that receive every contact form submission.
     *
     * @var list<string>
     */
    private const RECIPIENTS = [
        'yesmyshawarma@gmail.com',
        'yesmygrill@gmail.com',
        'info@yesmyshawarma.com',
    ];

    public function send(string $name, ?string $email, ?string $phone, string $message): void
    {
        Mail::to(self::RECIPIENTS)->send(
            new ContactMessageSubmitted($name, $email, $phone, $message)
        );
    }
}
