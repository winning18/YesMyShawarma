<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Reports\OrderReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Detailed reports" — defaults to the overall (cross-branch) picture for
 * owner/general_manager, same as Performance, rather than being silently
 * pinned to whatever branch happens to be ambient in session. A manager
 * sees only their own one branch either way, same as always, and never
 * gets the branch filter at all — there'd be nothing to filter.
 */
class ReportsController extends Controller
{
    private const MAX_RANGE_DAYS = 90;

    /**
     * @var list<string>
     */
    private const RANGE_PRESETS = ['today', '7', '30', 'week', 'month', 'last_month', 'custom'];

    public function index(Request $request, OrderReportService $reports, BranchContext $context): View
    {
        Gate::authorize('reports.view_operational');

        $user = $request->user();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $isGeneralManager = ! $isOwner && $context->hasRoleAtAnyBranch($user, 'general_manager');
        $crossBranch = $isOwner || $isGeneralManager;
        $scopeBranchIds = $isGeneralManager ? $context->branchIdsForRole($user, 'general_manager')->all() : null;

        $validated = $request->validate([
            'range' => ['nullable', Rule::in(self::RANGE_PRESETS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        [$from, $to] = $this->resolveRange($validated);

        // Same "tampered input dropped, never trusted" treatment as
        // PerformanceController — a manager/staff submitting a branch is
        // meaningless (already pinned to their own), and a
        // general_manager is further limited to branches they oversee.
        $filterBranchId = isset($validated['branch']) ? (int) $validated['branch'] : null;
        if (! $crossBranch || ($isGeneralManager && ! in_array($filterBranchId, $scopeBranchIds, true))) {
            $filterBranchId = null;
        }

        $canViewFinancial = Gate::allows('reports.view_financial');

        $utcFrom = $from->clone()->utc();
        $utcTo = $to->clone()->utc();

        // $filterBranchId set wins outright (one specific branch,
        // regardless of role); otherwise owner sees literally everything
        // and general_manager their oversight set — "overall" by default,
        // never silently narrowed by whatever's ambient in session. A
        // plain manager/staff falls through to BranchScope's own ambient
        // filtering, unchanged from before this existed.
        $branchArgs = $filterBranchId !== null
            ? ['branchId' => $filterBranchId]
            : ($crossBranch ? ['ignoreBranchScope' => $isOwner, 'branchIds' => $scopeBranchIds] : []);

        $branchOptionsQuery = Branch::orderBy('name');
        if ($isGeneralManager) {
            $branchOptionsQuery->whereIn('id', $scopeBranchIds);
        }

        return view('dashboard.reports.index', [
            'from' => $from,
            'to' => $to,
            'range' => $validated['range'] ?? null,
            'crossBranch' => $crossBranch,
            'branchFilterId' => $filterBranchId,
            'branchOptions' => $crossBranch ? $branchOptionsQuery->get(['id', 'name']) : null,
            'operational' => $reports->operationalSummary($utcFrom, $utcTo, ...$branchArgs),
            'financial' => $canViewFinancial ? $reports->financialSummary($utcFrom, $utcTo, ...$branchArgs) : null,
            'canViewFinancial' => $canViewFinancial,
        ]);
    }

    /**
     * A preset (other than 'custom') fully determines from/to and skips
     * the clamping/swap logic below — it's already a well-formed range.
     * Anything else (no range, or 'custom') falls through to the original
     * from/to handling unchanged, so every existing caller — including
     * every test written before 'range' existed — behaves exactly as
     * before.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $validated): array
    {
        $now = Carbon::now('Africa/Accra');

        $preset = match ($validated['range'] ?? null) {
            'today' => [$now->clone()->startOfDay(), $now->clone()->endOfDay()],
            '7' => [$now->clone()->subDays(6)->startOfDay(), $now->clone()->endOfDay()],
            '30' => [$now->clone()->subDays(29)->startOfDay(), $now->clone()->endOfDay()],
            'week' => [$now->clone()->startOfWeek(), $now->clone()->endOfWeek()],
            'month' => [$now->clone()->startOfMonth(), $now->clone()->endOfMonth()],
            'last_month' => [$now->clone()->subMonthNoOverflow()->startOfMonth(), $now->clone()->subMonthNoOverflow()->endOfMonth()],
            default => null,
        };

        if ($preset) {
            return $preset;
        }

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'], 'Africa/Accra')->endOfDay()
            : $now->clone()->endOfDay();

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'], 'Africa/Accra')->startOfDay()
            : $to->clone()->subDays(6)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->clone()->startOfDay(), $from->clone()->endOfDay()];
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->clone()->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        }

        return [$from, $to];
    }
}
