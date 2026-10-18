<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Carbon;

class SmsBroadcastException extends Exception
{
    public static function tooSoon(int $minDays, Carbon $nearest): self
    {
        return new self("Only one broadcast is allowed every {$minDays} days. The nearest one is on ".$nearest->timezone('Africa/Accra')->format('D j M, g:ia').'.');
    }

    public static function noRecipients(): self
    {
        return new self('There is nobody to send this broadcast to.');
    }

    public static function notCancellable(string $status): self
    {
        return new self("This broadcast is \"{$status}\" and can no longer be cancelled.");
    }
}
