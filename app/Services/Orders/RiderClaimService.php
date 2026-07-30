<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RiderClaimService
{
    /**
     * Atomic conditional update, not check-then-assign — a busy night has
     * multiple riders looking at the same "ready" order at once. The
     * WebSocket broadcast that greys the card out for other riders is
     * cosmetic; this UPDATE is what actually decides the claim. Zero rows
     * affected means another rider won it first, so the caller can show
     * "claimed by <name>" instead of a generic error. See orders.md.
     *
     * Claiming does not itself move the order out of "ready" — it only
     * reserves the rider. The rider (or staff) advances ready -> dispatched
     * separately, through OrderStateMachine, once collection happens.
     */
    public function claim(Order $order, User $rider): RiderClaimResult
    {
        return DB::transaction(function () use ($order, $rider) {
            $affected = DB::table('orders')
                ->where('id', $order->id)
                ->where('status', 'ready')
                ->whereNull('rider_id')
                ->update([
                    'rider_id' => $rider->id,
                    'claimed_at' => now(),
                    'updated_at' => now(),
                ]);

            $current = $order->fresh();

            if ($affected === 0) {
                return RiderClaimResult::alreadyClaimed($current?->rider?->name);
            }

            $current->events()->create([
                'from_status' => 'ready',
                'to_status' => 'ready',
                'actor_type' => 'rider',
                'actor_id' => $rider->id,
                'meta' => ['action' => 'claimed'],
            ]);

            return RiderClaimResult::success($current);
        });
    }
}
