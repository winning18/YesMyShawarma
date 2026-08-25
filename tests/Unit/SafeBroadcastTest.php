<?php

namespace Tests\Unit;

use App\Support\SafeBroadcast;
use Exception;
use Tests\TestCase;

/**
 * The whole point of this class — a broadcast failure (Reverb briefly
 * unreachable, a network hiccup) must never surface as a failure of the
 * business action it's attached to. afterCommit() runs its callback
 * immediately when there's no open transaction (Laravel's own behaviour),
 * so this can be exercised directly without a database.
 */
class SafeBroadcastTest extends TestCase
{
    public function test_a_throwing_dispatch_does_not_propagate(): void
    {
        $ran = false;

        SafeBroadcast::afterCommit(function () use (&$ran) {
            $ran = true;

            throw new Exception('Reverb is unreachable');
        });

        $this->assertTrue($ran, 'The dispatch closure should still have run.');
    }

    public function test_a_succeeding_dispatch_still_runs_normally(): void
    {
        $ran = false;

        SafeBroadcast::afterCommit(function () use (&$ran) {
            $ran = true;
        });

        $this->assertTrue($ran);
    }
}
