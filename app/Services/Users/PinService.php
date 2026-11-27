<?php

namespace App\Services\Users;

use App\Models\Terminal;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Settings\BusinessType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Staff ID + PIN sign-in at a counter (Express POS step 7b, owner
 * 2026-11-15): for till staff only, only on a device set up as a counter,
 * and only at that counter's branch. Managers set PINs; a PIN is hashed
 * like a password.
 *
 * At a pharmacy or shop (R3, owner 2026-11-27) managers, general managers
 * and the owner get a PIN too, to approve a price change or a return at
 * the till (approver()) without the cashier signing out. It never signs
 * them in at a counter: attempt() stays till staff only.
 */
class PinService
{
    public function __construct(
        private readonly BranchContext $context,
        private readonly StaffCodeService $codes,
    ) {}

    public function canHavePin(User $user): bool
    {
        return $this->context->runsATill($user) || $this->approvesAtTheTill($user);
    }

    /** A manager's PIN approves price changes and returns (pharmacies and shops). */
    public function approvesAtTheTill(User $user): bool
    {
        return ! app(BusinessType::class)->isRestaurant()
            && collect(['owner', 'general_manager', 'manager'])->contains(fn (string $role) => $this->context->hasRoleAtAnyBranch($user, $role));
    }

    public const APPROVAL_TRIES = 5;

    public const APPROVAL_LOCK_SECONDS = 900;

    /**
     * The manager whose Staff ID and PIN approve this at $branchId: an owner,
     * or a manager or general manager of that branch. Five wrong tries lock
     * that Staff ID for 15 minutes, as at counter sign-in.
     *
     * @throws ValidationException with the message on `approval`
     */
    public function approver(string $staffCode, string $pin, int $branchId): User
    {
        $key = 'pin-approval|'.StaffCodeService::normalize($staffCode).'|'.$branchId;
        if (RateLimiter::tooManyAttempts($key, self::APPROVAL_TRIES)) {
            throw ValidationException::withMessages(['approval' => __('Too many tries. Try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
        }

        $user = User::where('staff_code', StaffCodeService::normalize($staffCode))->first();
        $ok = $user && $user->pin && $user->suspended_at === null && Hash::check($pin, $user->pin)
            && in_array($this->context->primaryRoleFor($user, $branchId), ['owner', 'general_manager', 'manager'], true);

        if (! $ok) {
            RateLimiter::hit($key, self::APPROVAL_LOCK_SECONDS);

            throw ValidationException::withMessages(['approval' => __('That isn\'t a manager\'s Staff ID and PIN for this branch. Ask a manager to type theirs.')]);
        }

        RateLimiter::clear($key);

        return $user;
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

        // Counter sign-in is till staff only, never a manager's approval PIN.
        if (! $user || ! $user->pin || ! Hash::check($pin, $user->pin) || ! $this->context->runsATill($user)) {
            return null;
        }

        return $this->worksAt($user, (int) $counter->branch_id) ? $user : null;
    }

    private function worksAt(User $user, int $branchId): bool
    {
        return $this->context->branchIdsForRole($user, 'staff')->map(fn ($id) => (int) $id)->contains($branchId);
    }
}
