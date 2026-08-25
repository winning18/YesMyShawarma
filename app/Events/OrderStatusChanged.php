<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldBroadcastNow — see OrderPlaced's docblock for why. A late-arriving
 * status broadcast is the same "staff misses it" failure as a late order.
 */
class OrderStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly int $branchId,
        public readonly string $status,
        public readonly string $trackToken,
    ) {}

    /**
     * `branch.{id}.orders` is private (staff/managers/owner at that branch,
     * see routes/channels.php). `order.{track_token}` is deliberately a
     * plain public channel, not private — realtime.md's "authorises on
     * token possession alone, no login" is exactly what a public channel
     * already provides for free: knowing the channel name requires knowing
     * the random 32-char token, so the name itself is the authorization.
     * A genuinely anonymous *private* channel would need a custom
     * broadcasting-auth route bypassing Laravel's normal guard requirement
     * — this reading avoids that extra machinery.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->branchId}.orders"),
            new Channel("order.{$this->trackToken}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderStatusChanged';
    }

    /**
     * Identifiers and status only, never the full order — see realtime.md's
     * payload discipline. Consumers refetch on receipt; broadcasts are
     * cosmetic, never authoritative.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'status' => $this->status];
    }
}
