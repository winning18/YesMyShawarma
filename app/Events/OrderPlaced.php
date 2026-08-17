<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldBroadcastNow, not ShouldBroadcast — this must reach the dashboard
 * the instant an order is placed (staff missing it means a late
 * acceptance), and a *queued* broadcast is only ever delivered once a
 * queue worker actually processes it. This app has no persistent worker
 * running by default (QUEUE_CONNECTION=database, nothing consuming it),
 * so a queued broadcast here would silently sit undelivered — exactly the
 * "orders aren't showing up in real time" failure mode. The payload is
 * tiny (an id — realtime.md's payload discipline), so dispatching inline
 * costs nothing worth deferring to a worker for.
 */
class OrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly int $branchId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("branch.{$this->branchId}.orders")];
    }

    public function broadcastAs(): string
    {
        return 'OrderPlaced';
    }

    /**
     * Identifiers only, never the full order — see realtime.md's payload
     * discipline. The dashboard refetches on receipt.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId];
    }
}
