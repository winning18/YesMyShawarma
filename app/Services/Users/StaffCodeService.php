<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\Settings\BusinessProfile;

/**
 * Staff IDs for counter sign-in (Express POS step 7b): the business's
 * initials and a number — "YMS-0042" for Yes My Shawarma. Given to till
 * staff when their account is made; never reused.
 */
class StaffCodeService
{
    public function __construct(private readonly BusinessProfile $profile) {}

    public function prefix(): string
    {
        $words = preg_split('/[^A-Za-z]+/', $this->profile->name(), -1, PREG_SPLIT_NO_EMPTY);
        $initials = strtoupper(implode('', array_map(fn (string $word) => $word[0], $words)));

        if (strlen($initials) < 2) {
            $initials = strtoupper(substr(implode('', $words), 0, 3));
        }

        return substr($initials, 0, 4) ?: 'STF';
    }

    /** Gives the user a Staff ID if they don't have one. */
    public function ensureFor(User $user): string
    {
        if (! $user->staff_code) {
            $user->forceFill(['staff_code' => $this->next()])->save();
        }

        return $user->staff_code;
    }

    private function next(): string
    {
        $prefix = $this->prefix();

        $highest = User::withTrashed()->where('staff_code', 'like', $prefix.'-%')->pluck('staff_code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix) + 1))
            ->max() ?? 0;

        return sprintf('%s-%04d', $prefix, $highest + 1);
    }

    /** "yms-42" or "YMS0042" → "YMS-0042", for matching what staff type. */
    public static function normalize(string $typed): string
    {
        $typed = strtoupper(preg_replace('/\s+/', '', $typed));

        return preg_match('/^([A-Z]+)-?(\d+)$/', $typed, $m) ? sprintf('%s-%04d', $m[1], (int) $m[2]) : $typed;
    }
}
