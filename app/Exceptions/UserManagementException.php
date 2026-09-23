<?php

namespace App\Exceptions;

use Exception;

class UserManagementException extends Exception
{
    public static function riderHasActiveOrders(): self
    {
        return new self('This rider still has an order in progress at this branch — reassign or complete it before removing them.');
    }
}
