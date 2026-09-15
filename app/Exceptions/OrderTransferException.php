<?php

namespace App\Exceptions;

use Exception;

class OrderTransferException extends Exception
{
    public static function notEligible(string $status): self
    {
        return new self("An order in \"{$status}\" can no longer be transferred to another branch.");
    }

    public static function sameBranch(): self
    {
        return new self('This order is already at that branch.');
    }

    public static function destinationNotAccepting(): self
    {
        return new self('The destination branch is closed or not accepting orders right now.');
    }

    /**
     * @param  list<string>  $itemNames
     */
    public static function itemsUnavailable(array $itemNames): self
    {
        $names = implode(', ', $itemNames);

        return new self("The destination branch doesn't have: {$names}.");
    }
}
