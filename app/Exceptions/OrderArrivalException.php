<?php

namespace App\Exceptions;

use Exception;

class OrderArrivalException extends Exception
{
    public static function notADeliveryOrder(): self
    {
        return new self('Only a delivery order can be marked arrived.');
    }

    public static function notEligible(string $status): self
    {
        return new self("An order that is \"{$status}\" can't be marked arrived — it must be dispatched first.");
    }

    public static function alreadyArrived(): self
    {
        return new self('This order was already marked arrived.');
    }
}
