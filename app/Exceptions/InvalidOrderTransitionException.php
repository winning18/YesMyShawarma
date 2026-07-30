<?php

namespace App\Exceptions;

use Exception;

class InvalidOrderTransitionException extends Exception
{
    public static function forTransition(string $from, string $to): self
    {
        return new self("Order cannot transition from [{$from}] to [{$to}].");
    }

    public static function missingCancellationReason(): self
    {
        return new self('A cancellation_reason is required when cancelling an order.');
    }

    public static function missingActor(): self
    {
        return new self('actor_id is required unless actor_type is "system".');
    }
}
