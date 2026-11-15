<?php

namespace App\Services\Users;

use App\Models\Terminal;
use App\Models\User;
use App\Services\Branches\BranchContext;
use Illuminate\Support\Facades\Hash;

/**
 * Staff ID + PIN sign-in at a counter (Express POS step 7b, owner
 * 2026-11-15): for till staff only, only on a device set up as a counter,
 * and only at that counter's branch. Managers set PINs; a PIN is hashed
 * like a password.
 */
class PinService
{
    public function __construct(
        private readonly BranchContext $context,
        private readonly StaffCodeService $codes,
    ) {}

    public function canHavePin(User $user): bool
    {
        return $this->context->runsATill($user);
    }

    /**
     * @param  string  $pin  4 to 6 digits (validated by the caller)
     */
    public function set(User $user, string $pin): void
    {
        $this->codes->ensureFor($user);
        $user->forceFill(['pin' => Hash::make($pin)])->save();
    }

    public function clear(User $user): void
    {
        $user->forceFill(['pin' => null])->save();
    }

    /**
     * The staff member these details sign in at this counter, or null —
     * one answer for every failure, so a wrong ID and a wrong PIN look
     * the same.
     */
    public function attempt(string $staffCode, string $pin, Terminal $counter): ?User
    {
        $user = User::where('staff_code', StaffCodeService::normalize($staffCode))->first();

        if (! $user || ! $user->pin || ! Hash::check($pin, $user->pin) || ! $this->canHavePin($user)) {
            return null;
        }

        return $this->worksAt($user, (int) $counter->branch_id) ? $user : null;
    }

    private function worksAt(User $user, int $branchId): bool
    {
        return $this->context->branchIdsForRole($user, 'staff')->map(fn ($id) => (int) $id)->contains($branchId);
    }
}
