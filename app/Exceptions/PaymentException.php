<?php

namespace App\Exceptions;

use Exception;

class PaymentException extends Exception
{
    public static function wrongPaymentMethod(string $expected, string $actual): self
    {
        return new self("Cannot initialize a {$expected} payment for an order placed with payment_method \"{$actual}\".");
    }

    public static function duplicateTransactionReference(string $reference): self
    {
        return new self("Transaction ID \"{$reference}\" is already recorded against another payment.");
    }
}
