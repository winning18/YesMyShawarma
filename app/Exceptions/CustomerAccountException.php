<?php

namespace App\Exceptions;

use Exception;

class CustomerAccountException extends Exception
{
    public static function phoneTaken(): self
    {
        return new self(__('This number is already used by another account. Please call the branch.'));
    }

    public static function samePhone(): self
    {
        return new self(__('That is already your phone number.'));
    }

    public static function noAccount(): self
    {
        return new self(__('No account uses this number. Sign up instead, or check the number.'));
    }
}
