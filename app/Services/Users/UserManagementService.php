<?php

namespace App\Services\Users;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class UserManagementService
{
    /**
     * The owner is vouching for this account by creating it directly —
     * unlike self-registration, there's no reason to make them verify an
     * email they didn't type in themselves, so email_verified_at is set
     * immediately. The password is a throwaway random value the owner
     * never sees: the new user sets their own via the same password-reset
     * flow already built (Password::sendResetLink), rather than a
     * separate onboarding email/notification to maintain.
     *
     * @param  array{name: string, email: string, phone: ?string}  $data
     */
    public function create(array $data, string $role, int $branchId): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make(Str::random(40)),
        ]);

        // Not in User's #[Fillable(...)] on purpose — that allowlist guards
        // against mass-assigning this from untrusted HTTP input, which
        // doesn't apply to a value this service sets itself.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->assignRole($user, $role, $branchId);

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }

    public function assignRole(User $user, string $role, int $branchId): void
    {
        $this->withTeamId($branchId, fn () => $user->assignRole($role));
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
