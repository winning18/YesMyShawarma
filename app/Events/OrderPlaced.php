<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcast
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
