<?php

namespace App\Exceptions;

use Exception;

class ReviewException extends Exception
{
    public static function notYetEligible(): self
    {
        return new self('This order has not been completed yet — a review can only be left once it has.');
    }

    public static function alreadyReviewed(): self
    {
        return new self('This order already has a review.');
    }

    public static function invalidRating(): self
    {
        return new self('Rating must be between 1 and 5.');
    }

    public static function wrongStatus(string $expected, string $actual): self
    {
        return new self("This review is \"{$actual}\", not \"{$expected}\" — it can no longer be actioned this way.");
    }
}
