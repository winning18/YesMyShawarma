<?php

namespace App\Exceptions;

use Exception;

class DeliveryFeeCollectionException extends Exception
{
    public static function nothingToCollect(): self
    {
        return new self(__('This rider has no cash delivery fees left to hand in for these dates.'));
    }

    public static function alreadyCollected(): self
    {
        return new self(__('Some of these deliveries were just recorded as collected by someone else. Check the list and try again.'));
    }

    public static function alreadyVoided(): self
    {
        return new self(__('This collection was already voided.'));
    }
}
