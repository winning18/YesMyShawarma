<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Defers $dispatch until the enclosing transaction commits — same
 * reasoning as every DB::afterCommit call site this replaces: a broadcast
 * must never risk firing before the row it refers to actually exists —
 * and never lets a failed broadcast surface as a failure of the business
 * action it's attached to.
 *
 * This matters specifically because the broadcast events in this app are
 * ShouldBroadcastNow (dispatched inline, not queued — see OrderPlaced's
 * own docblock for why), which means there is no queue worker's
 * catch-and-log safety net between a broadcast failure and the request
 * that triggered it. Without this wrapper, Reverb being briefly
 * unreachable would turn into a 500 on an order that was otherwise placed
 * or updated successfully — broadcasts are cosmetic (realtime.md), never
 * allowed to take the actual transaction down with them.
 */
class SafeBroadcast
{
    public static function afterCommit(Closure $dispatch): void
    {
        DB::afterCommit(function () use ($dispatch) {
            try {
                $dispatch();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
