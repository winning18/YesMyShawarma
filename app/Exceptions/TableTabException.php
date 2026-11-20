<?php

namespace App\Exceptions;

use Exception;

class TableTabException extends Exception
{
    public static function tableTaken(string $name): self
    {
        return new self(__(':table already has an open bill.', ['table' => $name]));
    }

    public static function alreadySettled(): self
    {
        return new self(__('This bill was already settled.'));
    }

    public static function tableInactive(): self
    {
        return new self(__('Choose an active table at this branch.'));
    }

    public static function momoNeedsReference(): self
    {
        return new self(__('Enter the MoMo transaction ID from the customer\'s confirmation SMS.'));
    }

    public static function tableInUse(string $name): self
    {
        return new self(__(':table has an unpaid bill. Settle it before deleting the table.', ['table' => $name]));
    }

    public static function nameTaken(string $name): self
    {
        return new self(__('There is already a table called :table.', ['table' => $name]));
    }

    public static function openTabs(int $count): self
    {
        return new self(trans_choice('A table still has an unpaid bill. Settle it before closing the till.|:count tables still have unpaid bills. Settle them before closing the till.', $count, ['count' => $count]));
    }
}
