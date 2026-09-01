<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Performance\PerformanceReportService;
use App\Services\Reports\OrderReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The business-overview page — owner's landing "Dashboard" (cross-branch,
 * every branch aggregated), general_manager's (their own assigned set of
 * branches, aggregated — see $scopeBranchIds), and manager's (their one
 * current branch only; dashboard.performance is granted to all three, but
 * the scope is always decided by actual role, never by which branch
 * happens to be selected, same reasoning as the old OwnerOverviewController
 * this replaces). The "By branch" breakdown is owner/general_manager only:
 * a single-branch manager comparing branches would mean leaking other
 * branches' revenue.
 */
class PerformanceController extends Controller
{
    /**
     * @var array<string, int>
     */
    private const RANGE_DAYS = ['today' => 1, '7' => 7, '30' => 30];

    public function index(Request $request, PerformanceReportService $performance, OrderReportService $reports, BranchContext $context): View
    {
        Gate::authorize('dashboard.performance');

        $user = $request->user();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');

        // A general_manager is never also treated as owner even if they
        // somehow hold both — owner already gets everything via
        // Gate::before, so this only matters for which scope mode below
        // actually runs.
        $isGeneralManager = ! $isOwner && $context->hasRoleAtAnyBranch($user, 'general_manager');
        $scopeBranchIds = $isGeneralManager ? $context->branchIdsForRole($user, 'general_manager')->all() : null;

        // Whether this actor gets the cross-branch aggregate + "By branch"
        // comparison view at all (owner or general_manager), as opposed to
        // a regular manager's single current branch.
        $crossBranch = $isOwner || $isGeneralManager;
        $branchId = $context->id();

        $validated = $request->validate([
            'tab' => ['nullable', 'in:sales,operations,traffic'],
            'range' => ['nullable', 'in:today,7,30'],
            'sort' => ['nullable', 'in:amount_sold,item_sales'],
            'dir' => ['nullable', 'in:asc,desc'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $tab = $validated['tab'] ?? 'sales';

        // Traffic is site-wide, never branch-scoped (see
        // PerformanceReportService::visitorTraffic()'s docblock) — there's
        // no coherent "this branch's traffic" reading of the data, so
        // unlike the Sales/Operations tabs a plain manager or
        // general_manager never gets it, only the owner. Tampered input
        // is silently dropped back to Sales, same "not trusted" treatment
        // as a general_manager submitting another branch's id above.
        if ($tab === 'traffic' && ! $isOwner) {
            $tab = 'sales';
        }
        $rangeKey = $validated['range'] ?? '7';
        $days = self::RANGE_DAYS[$rangeKey];

        $to = Carbon::now('Africa/Accra')->endOfDay();
        $from = $to->clone()->subDays($days - 1)->startOfDay();

        // Drilling into a single branch is an owner/general_manager
        // capability — a manager is already pinned to their own branch,
        // so the value would be meaningless. For general_manager the
        // submitted branch must additionally be one of their own —
        // otherwise silently dropped back to the aggregate rather than
        // honoured, the same "tampered input is ignored, not trusted"
        // treatment WorkingHoursController gives a manager's 'branch'.
        // Cast before the strict in_array below — validate()'s 'integer'
        // rule checks the format but doesn't cast the query-string value,
        // which would otherwise never strictly match branchIdsForRole()'s
        // native ints.
        $filterBranchId = isset($validated['branch']) ? (int) $validated['branch'] : null;
        if (! $crossBranch || ($isGeneralManager && ! in_array($filterBranchId, $scopeBranchIds, true))) {
            $filterBranchId = null;
        }

        $branchOptionsQuery = Branch::orderBy('name');
        if ($isGeneralManager) {
            $branchOptionsQuery->whereIn('id', $scopeBranchIds);
        }

        $data = [
            'tab' => $tab,
            'rangeKey' => $rangeKey,
            'from' => $from,
            'to' => $to,
            'isOwner' => $isOwner,
            'crossBranch' => $crossBranch,
            'branchFilterId' => $filterBranchId,
            'branchOptions' => $crossBranch ? $branchOptionsQuery->get(['id', 'name']) : null,
        ];

        if ($tab === 'sales') {
            $sort = $validated['sort'] ?? 'amount_sold';
            $dir = $validated['dir'] ?? 'desc';

            $data['summary'] = $performance->salesSummary($from->clone()->utc(), $to->clone()->utc(), $isOwner, branchIds: $scopeBranchIds);
            $data['itemSales'] = $performance->itemSales($from->clone()->utc(), $to->clone()->utc(), $sort, $dir, ignoreBranchScope: $isOwner, branchId: $branchId, branchIds: $scopeBranchIds);
            $data['sort'] = $sort;
            $data['dir'] = $dir;
        } elseif ($tab === 'traffic') {
            $data['traffic'] = $performance->visitorTraffic($from->clone()->utc(), $to->clone()->utc());
        } else {
            $utcFrom = $from->clone()->utc();
            $utcTo = $to->clone()->utc();

            if ($filterBranchId !== null) {
                $data['operational'] = $reports->operationalSummary($utcFrom, $utcTo, ignoreBranchScope: false, branchId: $filterBranchId);
                $data['branches'] = null;
            } else {
                $data['operational'] = $reports->operationalSummary($utcFrom, $utcTo, ignoreBranchScope: $isOwner, branchIds: $scopeBranchIds);
                $data['branches'] = $crossBranch ? $this->branchBreakdown($reports, $utcFrom, $utcTo, $scopeBranchIds) : null;
            }
        }

        return view('dashboard.performance.index', $data);
    }

    /**
     * One operationalSummary()/financialSummary() call per branch, rather
     * than a bespoke aggregation here — reuses the exact same avg-time and
     * revenue logic the aggregate view and the single-branch drilldown
     * both already rely on, so the three views can never quietly disagree.
     *
     * @param  ?list<int>  $onlyBranchIds  Restricts the comparison to these branches
     *                                     (general_manager); null lists every branch (owner).
     * @return Collection<int, array{branch: Branch, orders: int, revenue: int, escalated: int, avg_time_to_accept_minutes: ?float, avg_prep_time_minutes: ?float, avg_delivery_time_minutes: ?float}>
     */
    private function branchBreakdown(OrderReportService $reports, Carbon $from, Carbon $to, ?array $onlyBranchIds = null): Collection
    {
        $branchesQuery = Branch::orderBy('name');
        if ($onlyBranchIds !== null) {
            $branchesQuery->whereIn('id', $onlyBranchIds);
        }

        return $branchesQuery->get()->map(function (Branch $branch) use ($reports, $from, $to) {
            $operational = $reports->operationalSummary($from, $to, ignoreBranchScope: false, branchId: $branch->id);
            $financial = $reports->financialSummary($from, $to, ignoreBranchScope: false, branchId: $branch->id);

            return [
                'branch' => $branch,
                'orders' => $operational['total_orders'],
                'revenue' => $financial['revenue_total'],
                'escalated' => $operational['escalations'],
                'avg_time_to_accept_minutes' => $operational['avg_time_to_accept_minutes'],
                'avg_prep_time_minutes' => $operational['avg_prep_time_minutes'],
                'avg_delivery_time_minutes' => $operational['avg_delivery_time_minutes'],
            ];
        });
    }
}
