<?php

namespace App\Exceptions;

use Exception;

class DamageReportException extends Exception
{
    public static function wrongStatus(string $expected, string $actual): self
    {
        return new self("This damage report is \"{$actual}\", not \"{$expected}\". It can no longer be actioned this way.");
    }

    public static function orderNotAssignedToRider(): self
    {
        return new self('This order is not currently assigned to you.');
    }
}
