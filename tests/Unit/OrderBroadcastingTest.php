<?php

namespace Tests\Unit;

use App\Events\OrderAssignedToRider;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\TestCase;

/**
 * Guards against silently reverting to ShouldBroadcast (queued) — this app
 * has no persistent queue worker running by default (QUEUE_CONNECTION=
 * database, nothing consuming it), so a queued broadcast is only ever
 * delivered once something processes the queue. Before this was caught,
 * 129 broadcast jobs had piled up undelivered — orders silently never
 * reached the live dashboard in real time. ShouldBroadcastNow dispatches
 * inline instead, so it can never depend on a worker existing.
 */
class OrderBroadcastingTest extends TestCase
{
    public function test_order_placed_broadcasts_immediately_not_via_queue(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new OrderPlaced(1, 1));
    }

    public function test_order_status_changed_broadcasts_immediately_not_via_queue(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new OrderStatusChanged(1, 1, 'accepted', 'token'));
    }

    public function test_order_assigned_to_rider_broadcasts_immediately_not_via_queue(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new OrderAssignedToRider(1, 1));
    }
}
