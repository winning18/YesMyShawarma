<?php

namespace App\Exceptions;

use Exception;

class RiderCashupException extends Exception
{
    public static function nothingToCollect(): self
    {
        return new self(__('This rider has nothing left to hand in for these dates.'));
    }

    public static function alreadyCollected(): self
    {
        return new self(__('Some of these deliveries were just handed in with another cash-up. Check the list and try again.'));
    }

    public static function alreadyVoided(): self
    {
        return new self(__('This cash-up was already voided.'));
    }
}
