<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\Branches\BranchContext;
use App\Services\Reports\DailySalesReportService;
use App\Services\Shifts\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Sales" tab (route/internal key still "today" — see _tabs.blade.php) —
 * gated by reports.view_operational (staff+), unlike Invoices and sales /
 * Weekly report which need reports.view_financial.
 *
 * Shift mode is the default and primary way this page is viewed: with no
 * params (and a resolved branch — staff/manager, or an owner/general_manager
 * who's switched to one), it shows whichever shift is currently open, or
 * the most recently closed one — never a calendar-day guess, which is what
 * made an overnight shift's own numbers depend on what time you happened to
 * look. An explicit `shift` id (the Shifts table's/Previous-Next shift
 * links) picks any other shift at the branch; `before()`/`after()`
 * (ShiftService) drive that navigation.
 *
 * Calendar mode (`date` param) is the explicit secondary lens for "what did
 * this branch sell on this specific calendar date" — still useful (e.g.
 * comparing one date across branches later), but never the default and
 * never confused with a shift's own report. Owner's cross-branch aggregate
 * view (no branch resolved at all) has no single branch's shift to default
 * to, so it falls back to calendar "today" until a branch is picked.
 *
 * See DailySalesReportService for the actual decomposition logic, which is
 * range-agnostic regardless of which mode produced the range.
 */
class TodayReportController extends Controller
{
    public function index(Request $request, DailySalesReportService $sales, ShiftService $shifts, BranchContext $context): View
    {
        Gate::authorize('reports.view_operational');

        $validated = $request->validate([
            // 'bolt_food' isn't a real `orders.channel` value — Bolt Food
            // orders are always channel 'pos' with payment_method
            // 'bolt_food' — this is a third UI-level view, not a database
            // column, translated to channel+payment_method below.
            'channel' => ['nullable', 'in:web,pos,bolt_food'],
            'date' => ['nullable', 'date'],
            'shift' => ['nullable', 'integer'],
        ]);

        $selected = $validated['channel'] ?? 'pos';
        $channel = $selected === 'bolt_food' ? 'pos' : $selected;
        $paymentMethod = $selected === 'bolt_food' ? 'bolt_food' : null;

        $branchId = $context->id();
        $isCalendarMode = isset($validated['date']);

        [$rangeStart, $rangeEnd, $date, $shift] = $isCalendarMode
            ? $this->resolveCalendarMode($validated)
            : $this->resolveShiftMode($validated, $branchId, $shifts);

        return view('dashboard.reports.today', [
            'channel' => $selected,
            'today' => $date,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'isCalendarMode' => $isCalendarMode,
            'shift' => $shift,
            'previousShift' => $shift ? $shifts->before($shift) : null,
            'nextShift' => $shift ? $shifts->after($shift) : null,
            'summary' => $sales->summary($rangeStart, $rangeEnd, $channel, $paymentMethod),
            // Shift model carries no BranchScope (ShiftService::
            // activeForBranch() takes an explicit branch id rather than
            // relying on it), so this filters explicitly rather than
            // relying on the global scope every other branch-owned query
            // here gets for free — null branch id (owner's cross-branch
            // aggregate view) means show every branch's shifts, mirroring
            // BranchScope's own no-op behaviour rather than silently
            // returning none. Only queried in calendar mode — shift mode
            // is reached by picking a shift from this very list (or by
            // landing on the default), so showing the list again
            // underneath it was redundant, not useful context.
            'shifts' => $isCalendarMode ? Shift::with(['user', 'endedBy', 'expenses'])
                ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
                ->whereBetween('started_at', [$date->clone()->startOfDay()->utc(), $date->clone()->endOfDay()->utc()])
                ->orderByDesc('started_at')
                ->get() : collect(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: null}
     */
    private function resolveCalendarMode(array $validated): array
    {
        $date = Carbon::parse($validated['date'], 'Africa/Accra');

        return [$date->clone()->startOfDay(), $date->clone()->endOfDay(), $date, null];
    }

    /**
     * An explicit `shift` id belonging to a *different* branch than the
     * one currently resolved is silently ignored rather than honoured —
     * same "tampered input dropped, never trusted" treatment
     * PerformanceController gives a general_manager submitting a branch
     * outside their own oversight set. A branch with no shift at all yet
     * (brand new, or simply never opened today) falls back to today's
     * calendar window so the page still renders something coherent
     * instead of an empty state with nothing to show.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: ?Shift}
     */
    private function resolveShiftMode(array $validated, ?int $branchId, ShiftService $shifts): array
    {
        $shift = isset($validated['shift']) ? Shift::find($validated['shift']) : null;

        if ($shift && $branchId !== null && $shift->branch_id !== $branchId) {
            $shift = null;
        }

        // Falls back to the branch's own current/most-recent shift both
        // when no id was given at all (the default landing view) and when
        // one was given but just got rejected above — a foreign shift id
        // is dropped, not allowed to leave the page in the "no shift
        // exists" state when the actual branch has a perfectly good one.
        $shift ??= $branchId ? $shifts->mostRecentForBranch($branchId) : null;

        if (! $shift) {
            $now = Carbon::now('Africa/Accra');

            return [$now->clone()->startOfDay(), $now->clone()->endOfDay(), $now, null];
        }

        $rangeStart = $shift->started_at->clone()->timezone('Africa/Accra');
        $rangeEnd = ($shift->ended_at ?? Carbon::now())->clone()->timezone('Africa/Accra');

        return [$rangeStart, $rangeEnd, $rangeStart->clone(), $shift];
    }
}
