<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWorkingHoursRequest;
use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Branches\WorkingHoursService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Working Hours" — its own sidebar item (navigation-links.blade.php),
 * not nested under Reports and invoices. Reuses reports.view_financial
 * (already owner+manager only, see permissions.md) rather than a
 * dedicated permission, since that's exactly the audience asked for.
 * Owner picks any branch via a selector (same pattern as the Performance
 * page's operations breakdown); manager is pinned to BranchContext::id(),
 * same as every other manager-scoped page.
 */
class WorkingHoursController extends Controller
{
    public function index(Request $request, BranchContext $context, WorkingHoursService $hours): View
    {
        Gate::authorize('reports.view_financial');

        $user = $request->user();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $branches = $isOwner ? $context->selectableBranchesFor($user)->sortBy('name')->values() : null;

        $validated = $request->validate([
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $branchId = $isOwner
            ? ($validated['branch'] ?? $branches->first()?->id)
            : $context->id();

        return view('dashboard.working-hours.index', [
            'isOwner' => $isOwner,
            'branches' => $branches,
            'branchId' => $branchId,
            'days' => $branchId ? $hours->forBranch($branchId) : collect(),
        ]);
    }

    public function update(UpdateWorkingHoursRequest $request, BranchContext $context, WorkingHoursService $hours): RedirectResponse
    {
        Gate::authorize('reports.view_financial');

        $isOwner = $context->hasRoleAtAnyBranch($request->user(), 'owner');
        $validated = $request->validated();
        $branchId = $isOwner ? $validated['branch'] ?? null : $context->id();

        abort_unless($branchId, 404);
        abort_if($isOwner && ! Branch::whereKey($branchId)->exists(), 404);

        $hours->save($branchId, $validated['days'] ?? []);

        return redirect()->route('dashboard.working-hours.index', $isOwner ? ['branch' => $branchId] : [])
            ->with('status', __('Working hours updated.'));
    }
}
