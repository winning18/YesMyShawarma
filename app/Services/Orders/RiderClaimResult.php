<?php

namespace App\Services\Orders;

use App\Models\Order;

class RiderClaimResult
{
    private function __construct(
        public readonly bool $claimed,
        public readonly ?Order $order,
        public readonly ?string $claimedByName,
    ) {}

    public static function success(Order $order): self
    {
        return new self(true, $order, null);
    }

    public static function alreadyClaimed(?string $claimedByName): self
    {
        return new self(false, null, $claimedByName);
    }
}
