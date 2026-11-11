<?php

namespace App\Exceptions;

use Exception;

class MarketListException extends Exception
{
    public static function alreadyApproved(): self
    {
        return new self('This market list is already approved and can no longer be changed.');
    }

    public static function notPending(): self
    {
        return new self('This market list is not waiting for approval.');
    }

    public static function noItems(): self
    {
        return new self('Add at least one item with an amount.');
    }
}
