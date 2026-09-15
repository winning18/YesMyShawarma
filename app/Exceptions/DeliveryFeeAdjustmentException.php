<?php

namespace App\Exceptions;

use Exception;

class DeliveryFeeAdjustmentException extends Exception
{
    public static function notDeliveryOrder(): self
    {
        return new self('Only a delivery order has a delivery fee to adjust.');
    }

    public static function notEligible(string $status): self
    {
        return new self("An order that is \"{$status}\" can no longer have its delivery fee adjusted.");
    }

    public static function preciselyPriced(): self
    {
        return new self("This order's delivery fee was already calculated from the customer's shared location — it isn't an estimate.");
    }
}
