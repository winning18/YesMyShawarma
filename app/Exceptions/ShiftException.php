<?php

namespace App\Exceptions;

use Exception;

class ShiftException extends Exception
{
    public static function mustBeOnShiftToAccept(): self
    {
        return new self('Start your shift before accepting orders.');
    }
}
