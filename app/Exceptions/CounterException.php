<?php

namespace App\Exceptions;

use Exception;

class CounterException extends Exception
{
    public static function sharedShiftOpen(): self
    {
        return new self(__('End the branch\'s open shift before adding its first counter.'));
    }

    public static function tillOpen(string $name): self
    {
        return new self(__(':counter\'s till is open. Close it before switching the counter off.', ['counter' => $name]));
    }

    public static function notACounter(): self
    {
        return new self(__('This device isn\'t set up as one of this branch\'s counters. Ask a manager to set it up.'));
    }
}
