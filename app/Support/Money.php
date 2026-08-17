<?php

namespace App\Support;

/**
 * The one place GHS (as typed by a human, in a form) becomes pesewas (as
 * stored, per CLAUDE.md's "money is integer minor units" rule). Everywhere
 * a controller converts a validated decimal amount before saving should go
 * through here instead of inlining (int) round($x * 100) — three
 * controllers did that independently before this existed, which is exactly
 * the kind of duplication where the formula drifts in one spot and not the
 * others.
 */
class Money
{
    public static function toPesewas(int|float|string $ghs): int
    {
        return (int) round(((float) $ghs) * 100);
    }
}
