<?php

namespace App\Exceptions;

use Exception;

class AccountSuspensionException extends Exception
{
    public static function notAllowed(): self
    {
        return new self("You can't suspend or restore this account.");
    }
}
