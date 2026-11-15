<?php

namespace App\Exceptions;

use Exception;

class OrderPlacementException extends Exception
{
    public static function customerSuspended(): self
    {
        return new self("This number can't place orders online. Please call the branch.");
    }

    /** A switched-off module's option (ModuleService), e.g. a Bolt Food order with Bolt Food off. */
    public static function moduleOff(string $what): self
    {
        return new self(__(':what isn\'t available.', ['what' => $what]));
    }

    /** A branch with counters takes POS sales only on one of them (CounterService). */
    public static function notACounter(): self
    {
        return new self(__('This device isn\'t set up as one of this branch\'s counters. Ask a manager to set it up.'));
    }

    public static function pickupOnly(): self
    {
        return new self(__('Freebies and WhatsApp group orders can only be pickup orders placed at the POS.'));
    }

    public static function freebieDetailsRequired(): self
    {
        return new self(__('Say who the freebie is for and why.'));
    }

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

    public static function invalidDeliveryArea(): self
    {
        return new self('Please select a valid delivery area.');
    }

    public static function belowMinimumOrderTotal(int $minOrderTotalPesewas): self
    {
        $cedis = number_format($minOrderTotalPesewas / 100, 2);

        return new self("Minimum order for delivery is GHS {$cedis}.");
    }

    public static function invalidPromoCode(string $reason): self
    {
        return new self($reason);
    }

    public static function duplicateMomoReference(string $reference): self
    {
        return new self("MoMo transaction ID \"{$reference}\" is already recorded against another order. Check the ID, or leave it blank and add it later.");
    }
}
