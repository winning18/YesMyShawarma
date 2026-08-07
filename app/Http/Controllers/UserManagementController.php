<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\Users\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const ROLES = ['staff', 'rider', 'manager', 'owner'];

    public function index(UserManagementService $users): View
    {
        Gate::authorize('users.manage');

        return view('dashboard.users.index', [
            'users' => User::orderBy('name')->get()->map(fn (User $user) => [
                'user' => $user,
                'roles' => $users->rolesFor($user),
            ]),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('users.manage');

        return view('dashboard.users.create', [
            'branches' => Branch::orderBy('name')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(CreateUserRequest $request, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validated();

        $user = $users->create($validated, $validated['role'], (int) $validated['branch_id']);

        return redirect()->route('dashboard.users.edit', $user)
            ->with('status', __(':name has been created and sent a link to set their password.', ['name' => $user->name]));
    }

    public function edit(User $user, UserManagementService $users): View
    {
        Gate::authorize('users.manage');

        return view('dashboard.users.edit', [
            'targetUser' => $user,
            'roles' => $users->rolesFor($user),
            'availableRoles' => self::ROLES,
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function addRole(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(self::ROLES)],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $users->assignRole($user, $validated['role'], (int) $validated['branch_id']);

        return back()->with('status', __('Role added.'));
    }

    public function removeRole(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(self::ROLES)],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        // A locked-out owner has no self-service way back in — this is the
        // one guardrail worth the friction, unlike everything else here
        // which trusts the owner's judgement.
        if ($user->id === $request->user()->id && $validated['role'] === 'owner') {
            return back()->withErrors(['role' => __('You cannot remove your own owner role.')]);
        }

        $users->removeRole($user, $validated['role'], (int) $validated['branch_id']);

        return back()->with('status', __('Role removed.'));
    }
}
