<?php

namespace App\Jobs;

use App\Contracts\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One SMS (customer order updates, owner low-stock alerts), sent from the
 * queue rather than inside the staff or rider request that triggered it.
 * Arkesel has gone unresponsive for minutes at a time (Sentry
 * PHP-LARAVEL-M/N, Oct 2026), and inline, each attempt held the person
 * tapping "Delivered" for up to 15 seconds (5s connect + 10s response).
 *
 * A failed send is tried again after 1, then 5 minutes, so a short
 * Arkesel outage delays a message instead of losing it. The send itself
 * still never throws — see ArkeselNotifier. A timed-out attempt may in
 * rare cases have reached Arkesel anyway, so a retry can occasionally
 * double up; a late or repeated status SMS is the lesser harm.
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds before the 2nd and 3rd attempts */
    public array $backoff = [60, 300];

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
        if ($notifier->send($this->phone, $this->message, $this->context)) {
            return;
        }

        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? end($this->backoff));

            return;
        }

        Log::warning('SMS not delivered after all attempts', ['phone' => $this->phone, 'attempts' => $this->attempts(), ...$this->context]);
    }
}
