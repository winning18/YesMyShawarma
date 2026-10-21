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
}
