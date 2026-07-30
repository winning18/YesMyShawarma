<?php

namespace App\Exceptions;

use Exception;

class ShiftException extends Exception
{
    public static function alreadyOnShift(): self
    {
        return new self('You already have an open shift — end it before starting another.');
    }
}
