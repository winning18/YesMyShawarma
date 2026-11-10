<?php

namespace App\Services\Accounts;

use App\Exceptions\AccountSuspensionException;
use App\Models\Customer;
use App\Models\User;
use App\Services\Branches\BranchContext;
use Illuminate\Support\Facades\DB;

/**
 * Suspending and restoring accounts (owner, 2026-11-10) — staff, riders and
 * managers (users) and customers alike. A suspended account:
 * - can't log in on any guard (the three LoginRequests check isSuspended()),
 * - is signed out on its very next request (EnsureAccountNotSuspended),
 * - loses any app (Sanctum) tokens and, for users, its stored sessions,
 * - gets no rider assignments or escalation/stock SMS (BranchContext::
 *   usersWithRole, RiderAssignmentService skip it),
 * - and, for a customer, can't place web orders by phone either
 *   (OrderCreationService) — otherwise guest checkout would get round it.
 *
 * Who may suspend whom is decided here, not in the controllers: the owner
 * anyone but another owner; a general manager only staff/riders whose every
 * role is at a branch they manage, plus customers. Nobody suspends
 * themselves, and nobody can suspend an owner, so the business can never
 * be locked out of its own dashboard.
 */
class AccountSuspensionService
{
    public function __construct(private readonly BranchContext $context) {}

    public function isSuspended(User|Customer $account): bool
    {
        return $account->suspended_at !== null;
    }

    public function canSuspendUser(User $actor, User $target): bool
    {
        if ($actor->is($target) || $this->context->hasRoleAtAnyBranch($target, 'owner')) {
            return false;
        }

        if ($this->context->hasRoleAtAnyBranch($actor, 'owner')) {
            return true;
        }

        if (! $this->context->hasRoleAtAnyBranch($actor, 'general_manager')) {
            return false;
        }

        $managed = $this->context->branchIdsForRole($actor, 'general_manager')->map(fn ($id) => (int) $id)->all();
        $assignments = DB::table(config('permission.table_names.model_has_roles').' as mhr')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'mhr.role_id')
            ->where('mhr.model_id', $target->id)
            ->where('mhr.model_type', $target->getMorphClass())
            ->get(['roles.name', 'mhr.'.config('permission.column_names.team_foreign_key').' as branch_id']);

        return $assignments->isNotEmpty() && $assignments->every(
            fn ($row) => in_array($row->name, ['staff', 'rider'], true) && in_array((int) $row->branch_id, $managed, true)
        );
    }

    public function canSuspendCustomers(User $actor): bool
    {
        return $this->context->hasRoleAtAnyBranch($actor, 'owner')
            || $this->context->hasRoleAtAnyBranch($actor, 'general_manager');
    }

    /**
     * @throws AccountSuspensionException
     */
    public function suspend(User|Customer $account, User $by, string $reason): void
    {
        $this->authorize($account, $by);

        DB::transaction(function () use ($account, $by, $reason) {
            $account->forceFill(['suspended_at' => now(), 'suspended_by' => $by->id, 'suspension_reason' => $reason])->save();
            $account->tokens()->delete();

            if ($account instanceof User) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $account->id)->delete();
            }
        });
    }

    /**
     * @throws AccountSuspensionException
     */
    public function lift(User|Customer $account, User $by): void
    {
        $this->authorize($account, $by);

        $account->forceFill(['suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
    }

    private function authorize(User|Customer $account, User $by): void
    {
        $allowed = $account instanceof User
            ? $this->canSuspendUser($by, $account)
            : $this->canSuspendCustomers($by);

        if (! $allowed) {
            throw AccountSuspensionException::notAllowed();
        }
    }
}
