<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\BranchContext;
use Illuminate\Http\Request;

/**
 * The branch filter on every Reports and invoices tab — one rule for who
 * may see which branch. Owner: every branch. General manager and manager:
 * only the branches they hold that role at, never another branch's
 * figures. Staff: no filter, pinned to their current branch by BranchScope.
 *
 * A submitted branch outside someone's set is dropped, never trusted. With
 * no branch chosen, the view is "overall" for that person's set rather
 * than whatever branch happens to be ambient in session.
 *
 * The branch arguments it returns follow WeeklySalesReportService's
 * convention (see ReportBranchResolver).
 */
class ReportBranchFilter
{
    public function __construct(private readonly BranchContext $context) {}

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function resolve(Request $request): array
    {
        $user = $request->user();
        $isOwner = $this->context->hasRoleAtAnyBranch($user, 'owner');
        $scope = $this->scopeFor($user);
        $crossBranch = $isOwner || $scope !== [];

        $validated = $request->validate(['branch' => ['nullable', 'integer', 'exists:branches,id']]);
        $filterBranchId = isset($validated['branch']) ? (int) $validated['branch'] : null;

        if (! $this->allows($isOwner, $scope, $filterBranchId)) {
            $filterBranchId = null;
        }

        $branchArgs = match (true) {
            $filterBranchId !== null => ['branchId' => $filterBranchId],
            $isOwner => ['ignoreBranchScope' => true, 'branchIds' => null],
            $crossBranch => ['ignoreBranchScope' => false, 'branchIds' => $scope],
            default => [],
        };

        return [$branchArgs, [
            'crossBranch' => $crossBranch,
            'branchFilterId' => $filterBranchId,
            'branchOptions' => $this->options($isOwner, $scope),
            'allBranchesLabel' => $this->allBranchesLabel($isOwner),
        ]];
    }

    /**
     * The branches a non-owner may pick. Empty for staff, and for an owner,
     * who isn't limited to a list at all.
     *
     * @return list<int>
     */
    public function scopeFor(User $user): array
    {
        if ($this->context->hasRoleAtAnyBranch($user, 'owner')) {
            return [];
        }

        return $this->context->branchIdsForRole($user, 'general_manager')
            ->merge($this->context->branchIdsForRole($user, 'manager'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $scope
     */
    public function allows(bool $isOwner, array $scope, ?int $branchId): bool
    {
        return $branchId !== null && ($isOwner || in_array($branchId, $scope, true));
    }

    /**
     * @param  list<int>  $scope
     */
    public function options(bool $isOwner, array $scope): mixed
    {
        if (! $isOwner && $scope === []) {
            return null;
        }

        return Branch::orderBy('name')
            ->when(! $isOwner, fn ($query) => $query->whereIn('id', $scope))
            ->get(['id', 'name']);
    }

    public function allBranchesLabel(bool $isOwner): string
    {
        return $isOwner ? __('All branches') : __('All my branches');
    }
}
