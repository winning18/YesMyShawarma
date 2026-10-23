<?php

namespace App\Jobs;

use App\Contracts\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One SMS (customer order updates, owner low-stock alerts), sent from the
 * queue rather than inside the staff or rider request that triggered it. Arkesel has gone
 * unresponsive for minutes at a time (Sentry PHP-LARAVEL-M/N, Oct 2026),
 * and inline, each attempt held the person tapping "Delivered" for up to
 * 15 seconds (5s connect + 10s response). The send itself still never
 * throws — see ArkeselNotifier.
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    public function handle(Notifier $notifier): void
    {
        $notifier->notify($this->phone, $this->message, $this->context);
    }
}
