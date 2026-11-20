<?php

namespace App\Exceptions;

use Exception;

class SmsBroadcastException extends Exception
{
    public static function noRecipients(): self
    {
        return new self('There is nobody to send this broadcast to.');
    }

    public static function notCancellable(string $status): self
    {
        return new self("This broadcast is \"{$status}\" and can no longer be cancelled.");
    }

    public static function notRetryable(): self
    {
        return new self('Only a sent broadcast with failed numbers can be retried.');
    }

    public static function nothingToRetry(): self
    {
        return new self('Everyone this broadcast could not reach has opted out since.');
    }

    public static function notResendable(string $status): self
    {
        return new self("This broadcast is \"{$status}\". Only a sent broadcast can be sent again.");
    }
}
