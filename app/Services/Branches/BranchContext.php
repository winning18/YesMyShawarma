<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class BranchContext
{
    public function __construct(private readonly Request $request) {}

    /**
     * Null whenever no session exists at all — console commands, queued
     * jobs, artisan tinker — not just when the session lacks the key. Those
     * contexts must resolve as "no branch", the same as a no-op BranchScope,
     * rather than throwing on session() with nothing attached to the request.
     */
    public function id(): ?int
    {
        if (! $this->request->hasSession()) {
            return null;
        }

        return $this->request->session()->get('current_branch_id');
    }

    public function branch(): ?Branch
    {
        $id = $this->id();

        return $id ? Branch::find($id) : null;
    }

    public function setCurrent(?int $branchId): void
    {
        if ($branchId === null) {
            $this->request->session()->forget('current_branch_id');

            return;
        }

        $this->request->session()->put('current_branch_id', $branchId);
    }

    /**
     * Every branch_id the user holds any role at, regardless of which role.
     */
    public function branchIdsFor(User $user): Collection
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_id', $user->id)
            ->where('model_type', $user->getMorphClass())
            ->pluck(config('permission.column_names.team_foreign_key'))
            ->filter()
            ->unique()
            ->values();
    }

    public function branchesFor(User $user): Collection
    {
        return Branch::whereIn('id', $this->branchIdsFor($user))->get();
    }

    /**
     * spatie's teams mode makes model_has_roles.branch_id NOT NULL and part of
     * its primary key — there is no such thing as a team-unscoped role row, so
     * $user->hasRole() only ever matches once a specific team id is already
     * set. Owner needs to be recognised before any branch id is resolved, so
     * this checks role membership at any branch directly against the pivot,
     * bypassing spatie's team-scoped relation entirely.
     */
    public function hasRoleAtAnyBranch(User $user, string $role): bool
    {
        return DB::table(config('permission.table_names.model_has_roles').' as mhr')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'mhr.role_id')
            ->where('mhr.model_id', $user->id)
            ->where('mhr.model_type', $user->getMorphClass())
            ->where('roles.name', $role)
            ->where('roles.guard_name', 'web')
            ->exists();
    }

    /**
     * Every user holding $role, at $branchId if given, or anywhere at all
     * when null — needed for owner, whose role assignment is anchored at
     * just one branch (see hasRoleAtAnyBranch) but who should still be
     * found regardless of which branch an escalation happens to be for.
     */
    public function usersWithRole(string $role, ?int $branchId = null): Collection
    {
        $query = DB::table(config('permission.table_names.model_has_roles').' as mhr')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'mhr.role_id')
            ->where('roles.name', $role)
            ->where('roles.guard_name', 'web')
            ->where('mhr.model_type', (new User)->getMorphClass());

        if ($branchId !== null) {
            $query->where('mhr.branch_id', $branchId);
        }

        $userIds = $query->pluck('mhr.model_id')->unique();

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Which of $user's roles applies at $branchId specifically — for
     * order_events.actor_type, so the audit trail records "manager" or
     * "owner" rather than a flat "staff" regardless of who actually acted.
     */
    public function primaryRoleFor(User $user, int $branchId): string
    {
        if ($this->hasRoleAtAnyBranch($user, 'owner')) {
            return 'owner';
        }

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($branchId);

        try {
            foreach (['manager', 'staff', 'rider'] as $role) {
                if ($user->hasRole($role)) {
                    return $role;
                }
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        return 'staff';
    }
}
