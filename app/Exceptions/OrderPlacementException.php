<?php

namespace App\Exceptions;

use Exception;

class OrderPlacementException extends Exception
{
    public static function emptyCart(): self
    {
        return new self('An order must contain at least one item.');
    }

    public static function invalidQuantity(): self
    {
        return new self('Item quantity must be at least 1.');
    }

    public static function branchNotAccepting(): self
    {
        return new self('This branch is not currently accepting orders.');
    }

    public static function menuItemUnavailable(string $name): self
    {
        return new self("\"{$name}\" is not available at this branch right now.");
    }

    public static function invalidOptionSelection(string $context): self
    {
        return new self("Invalid option selection: {$context}.");
    }

    public static function deliveryAddressRequired(): self
    {
        return new self('A delivery address is required for delivery orders.');
    }

    public static function noDeliveryZoneMatch(): self
    {
        return new self("This address is outside the branch's delivery area.");
    }

    public static function belowZoneMinimum(int $minOrderTotalPesewas): self
    {
        $cedis = number_format($minOrderTotalPesewas / 100, 2);

        return new self("Minimum order for delivery to this address is GHS {$cedis}.");
    }
}
