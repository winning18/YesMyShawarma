<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldBroadcastNow — see OrderPlaced's docblock for why.
 */
class OrderAssignedToRider implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly int $riderId,
    ) {}

    /**
     * Assignment always targets exactly one person — there's no claim pool
     * to broadcast to (see orders.md's rider assignment section), so this
     * goes straight to that rider's own private channel rather than a
     * branch-wide one.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->riderId}")];
    }

    public function broadcastAs(): string
    {
        return 'OrderAssignedToRider';
    }

    /**
     * Identifiers only — see realtime.md's payload discipline. The rider
     * dashboard refetches on receipt.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId];
    }
}
