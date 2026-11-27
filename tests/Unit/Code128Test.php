<?php

namespace Tests\Unit;

use App\Support\Code128;
use PHPUnit\Framework\TestCase;

/** The receipt barcode (R3): a well-formed Code 128 set B symbol. */
class Code128Test extends TestCase
{
    public function test_every_pattern_is_eleven_modules_and_unique(): void
    {
        $this->assertCount(107, Code128::PATTERNS);
        foreach (Code128::PATTERNS as $value => $pattern) {
            $this->assertSame($value === 106 ? 13 : 11, array_sum(str_split($pattern)), "pattern {$value}");
        }
        $this->assertCount(107, array_unique(Code128::PATTERNS));
    }

    public function test_a_receipt_number_encodes_with_start_checksum_and_stop(): void
    {
        // "PJ" : start B (104), P=48, J=42; checksum (104 + 48*1 + 42*2) % 103 = 30.
        $this->assertSame([104, 48, 42, 30, 106], Code128::values('PJ'));
        $this->assertStringContainsString('aria-label="POS-7K3QP9"', Code128::svg('POS-7K3QP9'));
    }
}
