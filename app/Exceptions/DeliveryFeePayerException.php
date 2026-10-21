<?php

namespace App\Exceptions;

use Exception;

class DeliveryFeePayerException extends Exception
{
    public static function notMomoDelivery(): self
    {
        return new self('Only a MoMo delivery order can change how its delivery fee is paid.');
    }

    public static function notEligible(string $status): self
    {
        return new self("An order that is \"{$status}\" can no longer change how its delivery fee is paid.");
    }
}
