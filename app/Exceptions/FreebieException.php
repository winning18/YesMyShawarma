<?php

namespace App\Exceptions;

use Exception;

class FreebieException extends Exception
{
    public static function notPending(): self
    {
        return new self(__('This freebie has already been reviewed.'));
    }
}
