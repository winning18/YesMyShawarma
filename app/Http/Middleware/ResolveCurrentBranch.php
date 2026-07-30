<?php

namespace App\Http\Middleware;

use App\Services\Branches\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentBranch
{
    public function __construct(private readonly BranchContext $context) {}

    /**
     * Owner sees a cross-branch view by default (the "implicitly all branches"
     * role from permissions.md), so they are never forced through selection —
     * they may still narrow to one branch via the switcher, which just leaves
     * a value already sitting in the session.
     *
     * Everyone else must resolve to exactly one branch before proceeding: if
     * they only hold roles at one branch it's picked automatically, if they
     * hold roles at several they're sent to pick, and having none at all is
     * a misconfigured account, not a silent all-branch view.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->context->hasRoleAtAnyBranch($user, 'owner')) {
            $branchIds = $this->context->branchIdsFor($user);

            abort_if($branchIds->isEmpty(), 403, 'Your account is not assigned to a branch.');

            if ($branchIds->count() === 1) {
                $this->context->setCurrent($branchIds->first());
            } elseif (! $branchIds->contains($this->context->id())) {
                return redirect()->route('branches.select');
            }
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->context->id());

        return $next($request);
    }
}
