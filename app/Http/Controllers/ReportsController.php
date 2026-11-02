<?php

namespace App\Http\Controllers;

use App\Services\Reports\OrderReportService;
use App\Services\Reports\ReportBranchFilter;
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
    private const RANGE_PRESETS = ['today', 'yesterday', '7', '30', 'week', 'month', 'last_month', 'custom'];

    public function index(Request $request, OrderReportService $reports, ReportBranchFilter $branches): View
    {
        Gate::authorize('reports.view_operational');

        $validated = $request->validate([
            'range' => ['nullable', Rule::in(self::RANGE_PRESETS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        [$from, $to] = $this->resolveRange($validated);

        [$branchArgs, $branchViewData] = $branches->resolve($request);

        $canViewFinancial = Gate::allows('reports.view_financial');

        $utcFrom = $from->clone()->utc();
        $utcTo = $to->clone()->utc();

        return view('dashboard.reports.index', [
            'from' => $from,
            'to' => $to,
            'range' => $validated['range'] ?? null,
            ...$branchViewData,
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
            'yesterday' => [$now->clone()->subDay()->startOfDay(), $now->clone()->subDay()->endOfDay()],
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
