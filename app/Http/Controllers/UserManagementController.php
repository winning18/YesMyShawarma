<?php

namespace App\Http\Controllers;

use App\Exceptions\UserManagementException;
use App\Http\Requests\CreateUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Accounts\AccountSuspensionService;
use App\Services\Branches\BranchContext;
use App\Services\Modules\ModuleService;
use App\Services\Users\PinService;
use App\Services\Users\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const ROLES = ['staff', 'rider', 'manager', 'general_manager', 'owner', 'stock_manager'];

    /**
     * The only roles a general_manager may create users into — see
     * authorizeCreate()/store(). Deliberately excludes 'manager',
     * 'general_manager' and 'owner': a general_manager oversees branches'
     * performance and staffs them, it does not appoint other managers.
     */
    private const OPERATIONAL_ROLES = ['staff', 'rider'];

    /**
     * The index/edit screens have three audiences now: an owner managing
     * the whole roster (users.manage — create, delete, add/remove any
     * role), a general_manager who can additionally create staff/rider
     * accounts (users.create_operational, see authorizeCreate/store), and
     * a plain manager who's here for exactly one thing, transferring a
     * staff/rider's branch (users.transfer_branch) — never create/delete/
     * role-editing access. All three need to reach these two GET actions;
     * everything else stays users.manage-only below.
     */
    private function authorizeView(Request $request, BranchContext $context): bool
    {
        $canManage = Gate::allows('users.manage');
        $user = $request->user();

        abort_unless(
            $canManage || $context->hasRoleAtAnyBranch($user, 'manager') || $context->hasRoleAtAnyBranch($user, 'general_manager'),
            403
        );

        return $canManage;
    }

    /**
     * Owner (unrestricted) or general_manager (staff/rider only, at a
     * branch they hold general_manager at — enforced in create()/store(),
     * not here) — never a plain manager, who has no user-creation ability
     * at all.
     */
    private function authorizeCreate(): bool
    {
        $canManage = Gate::allows('users.manage');

        abort_unless($canManage || Gate::allows('users.create_operational'), 403);

        return $canManage;
    }

    public function index(Request $request, UserManagementService $users, BranchContext $context): View
    {
        $canManage = $this->authorizeView($request, $context);

        return view('dashboard.users.index', [
            'canManage' => $canManage,
            'canCreate' => $canManage || Gate::allows('users.create_operational'),
            'users' => User::orderBy('name')->get()->map(fn (User $user) => [
                'user' => $user,
                'roles' => $users->rolesFor($user),
            ]),
        ]);
    }

    public function create(Request $request, BranchContext $context): View
    {
        $canManage = $this->authorizeCreate();

        $branches = $canManage
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $context->branchIdsForRole($request->user(), 'general_manager'))->orderBy('name')->get();

        return view('dashboard.users.create', [
            'branches' => $branches,
            'roles' => $this->offered($canManage ? self::ROLES : self::OPERATIONAL_ROLES),
        ]);
    }

    public function store(CreateUserRequest $request, UserManagementService $users, BranchContext $context): RedirectResponse
    {
        $canManage = $this->authorizeCreate();
        $validated = $request->validated();

        if (! $canManage) {
            // Server-side, not just a narrower dropdown — a general_manager
            // tampering the form must never be able to create a manager/
            // general_manager/owner account, or assign one at a branch
            // they don't hold general_manager at themselves.
            abort_unless(in_array($validated['role'], self::OPERATIONAL_ROLES, true), 403);
            abort_unless(
                $context->branchIdsForRole($request->user(), 'general_manager')->contains((int) $validated['branch_id']),
                403
            );
        }

        $result = $users->create($validated, $validated['role'], (int) $validated['branch_id']);
        $user = $result['user'];
        $temporaryPassword = $result['temporary_password'];

        return redirect()->route('dashboard.users.edit', $user)
            ->with('status', __(':name has been created.', ['name' => $user->name]))
            ->with('temporary_password', $temporaryPassword);
    }

    public function edit(Request $request, User $user, UserManagementService $users, BranchContext $context, AccountSuspensionService $suspensions): View
    {
        $canManage = $this->authorizeView($request, $context);

        return view('dashboard.users.edit', [
            'canManage' => $canManage,
            'canSuspend' => $suspensions->canSuspendUser($request->user(), $user),
            'suspendedBy' => $user->suspended_by ? User::withTrashed()->find($user->suspended_by)?->name : null,
            'targetUser' => $user,
            'roles' => $users->rolesFor($user),
            'availableRoles' => $this->offered(self::ROLES),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    /**
     * Sets or resets a till staff member's counter PIN (step 7b). Anyone
     * who manages users at all can; the PIN is never shown again.
     */
    public function setPin(Request $request, User $user, BranchContext $context, PinService $pins): RedirectResponse
    {
        $this->authorizeView($request, $context);
        abort_unless($pins->canHavePin($user), 422);

        $validated = $request->validate(['pin' => ['required', 'digits_between:4,6', 'confirmed']]);
        $pins->set($user, $validated['pin']);

        return back()->with('status', __('PIN set. :name signs in at a counter with :code and this PIN.', ['name' => $user->name, 'code' => $user->staff_code]));
    }

    public function clearPin(Request $request, User $user, BranchContext $context, PinService $pins): RedirectResponse
    {
        $this->authorizeView($request, $context);
        $pins->clear($user);

        return back()->with('status', __('PIN removed.'));
    }

    public function addRole(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in($this->offered(self::ROLES))],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $users->assignRole($user, $validated['role'], (int) $validated['branch_id']);

        return back()->with('status', __('Role added.'));
    }

    public function removeRole(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in($this->offered(self::ROLES))],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        // A locked-out owner has no self-service way back in — this is the
        // one guardrail worth the friction, unlike everything else here
        // which trusts the owner's judgement.
        if ($user->id === $request->user()->id && $validated['role'] === 'owner') {
            return back()->withErrors(['role' => __('You cannot remove your own owner role.')]);
        }

        try {
            $users->removeRole($user, $validated['role'], (int) $validated['branch_id']);
        } catch (UserManagementException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return back()->with('status', __('Role removed.'));
    }

    public function changeBranch(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in($this->offered(self::ROLES))],
            'from_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:from_branch_id'],
        ]);

        // Checked here, not in UserPolicy — AppServiceProvider's Gate::before
        // grants an owner actor every ability unconditionally, before any
        // policy method runs, which would otherwise let an owner transfer an
        // owner-role assignment through this action despite it never being
        // meant to be transferable at all, by anyone.
        abort_if($validated['role'] === 'owner', 403);

        // Extra args after $user go straight to UserPolicy::changeBranch —
        // the relational rules (self, manager-target, branch scope) live
        // there, not here.
        Gate::authorize('changeBranch', [$user, $validated['role'], (int) $validated['from_branch_id']]);

        try {
            $users->changeBranch(
                $user, $validated['role'], (int) $validated['from_branch_id'], (int) $validated['to_branch_id']
            );
        } catch (UserManagementException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        $toBranch = Branch::findOrFail($validated['to_branch_id']);

        return back()->with('status', __(':name has been moved to :branch.', ['name' => $user->name, 'branch' => $toBranch->name]));
    }

    public function destroy(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('users.manage');

        // Same reasoning as the owner-role guard in removeRole() — deleting
        // your own account out from under your own session is never the
        // intent, it's a misclick, and there's no self-service way back in.
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => __('You cannot delete your own account.')]);
        }

        $users->delete($user);

        return redirect()->route('dashboard.users.index')
            ->with('status', __(':name has been deleted.', ['name' => $user->name]));
    }

    /**
     * The rider role only while Delivery and riders is switched on
     * (ModuleService); existing riders keep their accounts either way.
     *
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function offered(array $roles): array
    {
        return app(ModuleService::class)->enabled(ModuleService::DELIVERY)
            ? $roles
            : array_values(array_diff($roles, ['rider']));
    }
}
