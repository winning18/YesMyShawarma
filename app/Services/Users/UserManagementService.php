<?php

namespace App\Services\Users;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserManagementService
{
    /**
     * Ambiguous characters (0/O, 1/I/L) excluded — this gets read off a
     * screen and typed back in by hand, often by whoever's creating the
     * account relaying it verbally or over WhatsApp to the new hire.
     */
    private const TEMP_PASSWORD_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The owner is vouching for this account by creating it directly —
     * unlike self-registration, there's no reason to make them verify an
     * email they didn't type in themselves, so email_verified_at is set
     * immediately. The password is a system-generated one-time value shown
     * once on the confirmation screen (see UserManagementController::store)
     * for the creating admin to relay directly — no dependency on email
     * being checked or on the still-undecided SMS provider (CLAUDE.md).
     * must_change_password forces them to replace it before they can reach
     * anything else (see EnsurePasswordIsChanged).
     *
     * @param  array{name: string, email: string, phone: ?string}  $data
     * @return array{user: User, temporary_password: string}
     */
    public function create(array $data, string $role, int $branchId): array
    {
        $temporaryPassword = $this->generateTemporaryPassword();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($temporaryPassword),
        ]);

        // Neither is in User's #[Fillable(...)] on purpose — that allowlist
        // guards against mass-assigning these from untrusted HTTP input,
        // which doesn't apply to values this service sets itself.
        $user->forceFill(['email_verified_at' => now(), 'must_change_password' => true])->save();

        $this->assignRole($user, $role, $branchId);

        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }

    private function generateTemporaryPassword(): string
    {
        $alphabet = self::TEMP_PASSWORD_ALPHABET;
        $password = '';

        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    /**
     * Soft delete (schema.md — users is one of the soft-deleted tables) —
     * the row stays so order_events/shifts/orders.rider_id keep pointing
     * at something real (shifts.user_id cascadeOnDelete and
     * orders.rider_id nullOnDelete would otherwise wipe attendance history
     * and blank out "who delivered this" on every past order a true hard
     * delete removed). What actually gets erased is the PII: email and
     * phone are scrubbed to a placeholder unique to this row's own id (both
     * columns carry a DB-level unique index — email is NOT nullable, so it
     * needs a guaranteed-unique replacement rather than null) so the real
     * address/number is immediately free to reuse on a new account, and
     * name is replaced so it stops showing up anywhere. A trashed user's
     * session simply stops resolving on their next request — the session
     * guard re-hydrates via a query that carries the SoftDeletingScope, so
     * there's no separate "log them out" step to build here.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update([
                'name' => 'Deleted user',
                'email' => "deleted-user-{$user->id}@deleted.invalid",
                'phone' => null,
            ]);

            $user->delete();
        });
    }

    public function assignRole(User $user, string $role, int $branchId): void
    {
        $this->withTeamId($branchId, fn () => $user->assignRole($role));
    }

    /**
     * A transfer, not two independent edits — Kofi moving from Odumase to
     * Pokuase should never be able to land holding neither (or, on a
     * partial failure, both). Authorization (self/manager-target/branch
     * scope) is UserPolicy::changeBranch's job, not this method's — by the
     * time this runs the caller has already decided it's allowed.
     */
    public function changeBranch(User $user, string $role, int $fromBranchId, int $toBranchId): void
    {
        DB::transaction(function () use ($user, $role, $fromBranchId, $toBranchId) {
            $this->removeRole($user, $role, $fromBranchId);
            $this->assignRole($user, $role, $toBranchId);
        });
    }

    public function removeRole(User $user, string $role, int $branchId): void
    {
        $this->withTeamId($branchId, fn () => $user->removeRole($role));
    }

    /**
     * Every role this user holds, at every branch — for the edit-roles
     * screen. Bypasses spatie's team-scoped relation the same way
     * BranchContext does, since there's no single ambient team id that
     * would return roles across every branch at once.
     *
     * @return Collection<int, array{role: string, branch: ?Branch}>
     */
    public function rolesFor(User $user): Collection
    {
        $teamKey = config('permission.column_names.team_foreign_key');

        return DB::table(config('permission.table_names.model_has_roles').' as mhr')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'mhr.role_id')
            ->where('mhr.model_id', $user->id)
            ->where('mhr.model_type', $user->getMorphClass())
            ->select('roles.name as role', "mhr.{$teamKey} as branch_id")
            ->get()
            ->map(fn ($row) => [
                'role' => $row->role,
                'branch' => $row->branch_id ? Branch::find($row->branch_id) : null,
            ]);
    }

    private function withTeamId(int $branchId, callable $callback): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($branchId);

        try {
            $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
