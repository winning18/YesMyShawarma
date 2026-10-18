<?php

namespace App\Jobs;

use App\Models\SmsBroadcast;
use App\Services\Sms\SmsBroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Sends one broadcast for up to SmsBroadcastService::SECONDS_PER_JOB, then
 * stops. sms:send-due-broadcasts queues another every minute while the
 * broadcast is still sending, so a large list finishes over several
 * minutes, and a job that dies part-way only ever resumes from the
 * recipients still pending.
 */
class SendSmsBroadcastBatch implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $broadcastId) {}

    /**
     * Never two jobs for one broadcast at once — they'd pick the same
     * pending rows. A duplicate is simply dropped; the next minute's
     * scheduler run queues a fresh one if anything is left.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->broadcastId))->dontRelease()->expireAfter(120)];
    }

    public function handle(SmsBroadcastService $broadcasts): void
    {
        $broadcast = SmsBroadcast::find($this->broadcastId);

        if ($broadcast) {
            $broadcasts->send($broadcast, now()->addSeconds(SmsBroadcastService::SECONDS_PER_JOB));
        }
    }
}
