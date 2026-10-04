<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\Branches\BranchContext;
use App\Services\Reports\DailySalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Today" tab — gated by reports.view_operational (staff+), unlike
 * Invoices and sales / Weekly report which need reports.view_financial.
 * Defaults to today (Africa/Accra) but is no longer locked to it: a
 * `date` param picks any single calendar day (with Previous/Next day
 * navigation in the view), and an explicit `from`/`to` pair — sent by the
 * Shifts table's own "View full report" link — scopes it to one exact
 * shift's started_at/ended_at window instead, which is what actually
 * matters for a shift that runs past midnight into the next calendar day.
 * See DailySalesReportService for the actual decomposition logic, which
 * was always range-agnostic even when this controller wasn't.
 */
class TodayReportController extends Controller
{
    public function index(Request $request, DailySalesReportService $sales, BranchContext $context): View
    {
        Gate::authorize('reports.view_operational');

        $validated = $request->validate([
            // 'bolt_food' isn't a real `orders.channel` value — Bolt Food
            // orders are always channel 'pos' with payment_method
            // 'bolt_food' — this is a third UI-level view, not a database
            // column, translated to channel+payment_method below.
            'channel' => ['nullable', 'in:web,pos,bolt_food'],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $selected = $validated['channel'] ?? 'pos';
        $channel = $selected === 'bolt_food' ? 'pos' : $selected;
        $paymentMethod = $selected === 'bolt_food' ? 'bolt_food' : null;

        [$rangeStart, $rangeEnd, $date, $isCustomRange] = $this->resolveRange($validated);

        return view('dashboard.reports.today', [
            'channel' => $selected,
            'today' => $date,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'isCustomRange' => $isCustomRange,
            'summary' => $sales->summary($rangeStart, $rangeEnd, $channel, $paymentMethod),
            // Shift model carries no BranchScope (ShiftService::
            // activeForBranch() takes an explicit branch id rather than
            // relying on it), so this filters explicitly rather than
            // relying on the global scope every other branch-owned query
            // here gets for free — null branch id (owner's cross-branch
            // aggregate view) means show every branch's shifts, mirroring
            // BranchScope's own no-op behaviour rather than silently
            // returning none. Still scoped to the whole calendar day even
            // when $isCustomRange — a single shift's own report should
            // still show it sitting alongside that day's other shifts for
            // context, not list itself in isolation.
            'shifts' => Shift::with(['user', 'endedBy', 'expenses'])
                ->when($context->id(), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->whereBetween('started_at', [$date->clone()->startOfDay()->utc(), $date->clone()->endOfDay()->utc()])
                ->orderByDesc('started_at')
                ->get(),
        ]);
    }

    /**
     * $from+$to together (the Shifts table's "View full report" link)
     * take priority over $date and produce an exact, not-whole-day range
     * — $date here is only for the Shifts list query and the page
     * heading, derived from $from so it still lands on the right day.
     * Otherwise $date (defaulting to today) drives a normal whole-day
     * range, same as before this existed.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: bool}
     */
    private function resolveRange(array $validated): array
    {
        if (isset($validated['from']) && isset($validated['to'])) {
            $from = Carbon::parse($validated['from'], 'Africa/Accra');
            $to = Carbon::parse($validated['to'], 'Africa/Accra');

            return [$from, $to, $from->clone(), true];
        }

        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'], 'Africa/Accra')
            : Carbon::now('Africa/Accra');

        return [$date->clone()->startOfDay(), $date->clone()->endOfDay(), $date, false];
    }
}
