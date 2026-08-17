<?php

namespace App\Exceptions;

use Exception;

class RefundException extends Exception
{
    public static function orderNotPaid(): self
    {
        return new self('Only a paid order can be refunded.');
    }

    public static function amountExceedsRemainingBalance(int $remainingPesewas): self
    {
        $remaining = number_format($remainingPesewas / 100, 2);

        return new self("Refund amount exceeds the GH₵{$remaining} still refundable on this order.");
    }

    public static function wrongStatus(string $expected, string $actual): self
    {
        return new self("This refund is \"{$actual}\", not \"{$expected}\" — it can no longer be actioned this way.");
    }
}
