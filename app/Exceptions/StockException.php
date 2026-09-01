<?php

namespace App\Exceptions;

use Exception;

class StockException extends Exception
{
    public static function insufficientStock(float $remaining, string $unit): self
    {
        return new self("Only {$remaining} {$unit} left in stock.");
    }
}
