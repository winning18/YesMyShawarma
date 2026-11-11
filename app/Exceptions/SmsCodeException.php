<?php

namespace App\Exceptions;

use Exception;

class SmsCodeException extends Exception
{
    public static function tooManyRequests(int $minutes): self
    {
        return new self(trans_choice('Too many codes requested. Try again in :count minute.|Too many codes requested. Try again in :count minutes.', $minutes, ['count' => $minutes]));
    }

    public static function notSent(): self
    {
        return new self(__('We couldn\'t send the code. Please check the number and try again.'));
    }

    public static function invalid(): self
    {
        return new self(__('That code is wrong or has expired.'));
    }

    public static function tooManyAttempts(): self
    {
        return new self(__('Too many wrong codes. Request a new one.'));
    }
}
