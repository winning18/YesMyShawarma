<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shift;
use App\Services\Branches\BranchContext;
use App\Services\Reports\DailySalesReportService;
use App\Services\Reports\DeliveryFeeReportService;
use App\Services\Reports\ReportBranchFilter;
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
 * Calendar mode (`date` param, or `from`+`to` for a genuine multi-day
 * range) is the explicit secondary lens for "what did this branch sell in
 * this calendar window" — still useful (e.g. comparing dates across
 * branches later, or totalling a week for reconciliation), but never the
 * default and never confused with a shift's own report. `date` alone is
 * just the single-day case of this same mode (from === to), kept as its
 * own param rather than folded into from/to so every existing deep link
 * (Performance's "shifts that day" card, the Today/Yesterday links below)
 * keeps working unchanged. Owner's cross-branch aggregate view (no branch
 * resolved at all, or the "all" branch filter) has no single branch's
 * shift to default to, so it shows
 * the calendar day of the latest shift at any branch — today once any
 * shift has started, otherwise the last day that had one — and the
 * shifts/expenses table follows the same day, across every branch.
 *
 * See DailySalesReportService for the actual decomposition logic, which is
 * range-agnostic regardless of which mode produced the range.
 */
class TodayReportController extends Controller
{
    public function index(Request $request, DailySalesReportService $sales, ShiftService $shifts, BranchContext $context, DeliveryFeeReportService $deliveryFees, ReportBranchFilter $branchFilter): View
    {
        Gate::authorize('reports.view_operational');

        $user = $request->user();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $scope = $branchFilter->scopeFor($user);
        $crossBranch = $isOwner || $scope !== [];

        $validated = $request->validate([
            // 'bolt_food' isn't a real `orders.channel` value — Bolt Food
            // orders are always channel 'pos' with payment_method
            // 'bolt_food' — this is a third UI-level view, not a database
            // column, translated to channel+payment_method below.
            'channel' => ['nullable', 'in:web,pos,bolt_food'],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from'],
            'shift' => ['nullable', 'integer'],
            'branch' => ['nullable', 'regex:/^(all|\d+)$/'],
        ]);

        $selected = $validated['channel'] ?? 'pos';
        $channel = $selected === 'bolt_food' ? 'pos' : $selected;
        $paymentMethod = $selected === 'bolt_food' ? 'bolt_food' : null;

        // ReportBranchFilter's rule: a branch outside someone's set is
        // dropped, never trusted. Unlike the other tabs, no choice at all
        // means the current branch, not "all" — this page is built around
        // that branch's open shift. "all" is the explicit overall view.
        // Takes precedence over the ambient session branch when present,
        // which is also what lets an owner/GM drill into one branch from
        // Performance without switching their session branch.
        $allBranches = ($validated['branch'] ?? null) === 'all' && $crossBranch;
        $requestedBranchId = ctype_digit((string) ($validated['branch'] ?? '')) ? (int) $validated['branch'] : null;
        if (! $branchFilter->allows($isOwner, $scope, $requestedBranchId)) {
            $requestedBranchId = null;
        }
        $branchId = $allBranches ? null : ($requestedBranchId ?? $context->id());
        $scopeIds = $allBranches && ! $isOwner ? $scope : null;

        $isRangeMode = isset($validated['from']) && isset($validated['to']);
        $isCalendarMode = $isRangeMode || isset($validated['date']);

        [$rangeStart, $rangeEnd, $date, $shift] = $isCalendarMode
            ? $this->resolveCalendarMode($validated)
            : $this->resolveShiftMode($validated, $branchId, $scopeIds, $shifts);

        return view('dashboard.reports.today', [
            'channel' => $selected,
            'today' => $date,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'isCalendarMode' => $isCalendarMode,
            'isRangeMode' => $isRangeMode,
            'shift' => $shift,
            'previousShift' => $shift ? $shifts->before($shift) : null,
            'nextShift' => $shift ? $shifts->after($shift) : null,
            'viewingBranch' => $requestedBranchId ? Branch::find($requestedBranchId) : null,
            'crossBranch' => $crossBranch,
            'allBranches' => $allBranches,
            'selectedBranchId' => $branchId,
            'branchOptions' => $branchFilter->options($isOwner, $scope),
            'allBranchesLabel' => $branchFilter->allBranchesLabel($isOwner),
            'summary' => $sales->summary($rangeStart, $rangeEnd, $channel, $paymentMethod, $requestedBranchId, $allBranches && $isOwner, $scopeIds),
            // Shown apart from sales, and never to staff — delivery fee
            // figures are for the owner, managers and the rider themselves
            // (same access as the Delivery fees tab). Dated by delivery,
            // like that tab. Bolt Food's courier isn't ours, so no line there.
            'deliveryFees' => $user->can('reports.view_financial') && $selected !== 'bolt_food'
                ? $deliveryFees->summary(
                    $deliveryFees->rows($rangeStart, $rangeEnd, ignoreBranchScope: $branchId === null && $scopeIds === null, branchId: $branchId, branchIds: $scopeIds)->where('channel', $channel)
                )
                : null,
            // Shift model carries no BranchScope (ShiftService::
            // activeForBranch() takes an explicit branch id rather than
            // relying on it), so this filters explicitly rather than
            // relying on the global scope every other branch-owned query
            // here gets for free — null branch id (owner's cross-branch
            // aggregate view) means show every branch's shifts, mirroring
            // BranchScope's own no-op behaviour rather than silently
            // returning none.
            //
            // Shift mode shows this same table too now, just as a single
            // row for $shift itself — expenses need to be visible
            // regardless of which lens is active, not only once someone
            // thinks to switch to calendar mode. The business-wide
            // fallback (no branch resolved at all, so no single "most
            // recent shift" concept applies — resolveShiftMode() already
            // falls back to the latest shift's calendar day for the
            // summary figures above) gets the equivalent of calendar mode's own
            // query instead of an empty table, for the exact same reason:
            // real shifts/expenses existing that day shouldn't disappear
            // just because nobody switched to one specific branch.
            'shifts' => match (true) {
                $isCalendarMode => Shift::with(['user', 'endedBy', 'expenses'])
                    ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
                    ->when($scopeIds !== null, fn ($query) => $query->whereIn('branch_id', $scopeIds))
                    ->whereBetween('started_at', [$rangeStart->clone()->utc(), $rangeEnd->clone()->utc()])
                    ->orderByDesc('started_at')
                    ->get(),
                $shift !== null => collect([$shift]),
                $branchId === null => Shift::with(['user', 'endedBy', 'expenses'])
                    ->when($scopeIds !== null, fn ($query) => $query->whereIn('branch_id', $scopeIds))
                    ->whereBetween('started_at', [$rangeStart->clone()->utc(), $rangeEnd->clone()->utc()])
                    ->orderByDesc('started_at')
                    ->get(),
                default => collect(),
            },
        ]);
    }

    /**
     * `from`+`to` (the date-range picker's calendar) takes priority over a
     * bare `date` when somehow both are present — same "explicit range
     * wins outright" rule OrderHistoryController/ReportsController apply.
     * A `from`/`to` picked the same day collapses to exactly the single-
     * day case below, so there's no behavioural seam between the two.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: null}
     */
    private function resolveCalendarMode(array $validated): array
    {
        if (isset($validated['from']) && isset($validated['to'])) {
            $from = Carbon::parse($validated['from'], 'Africa/Accra')->startOfDay();
            $to = Carbon::parse($validated['to'], 'Africa/Accra')->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to->clone()->startOfDay(), $from->clone()->endOfDay()];
            }

            return [$from, $to, $from->clone(), null];
        }

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
     * instead of an empty state with nothing to show. With no branch
     * resolved (the "all" filter, or an owner with none selected), the
     * window is the calendar day of the latest shift across those branches,
     * so the view has something to show even before today's first shift.
     *
     * @param  ?list<int>  $scopeIds  a manager/GM's own branches on "all"
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: ?Shift}
     */
    private function resolveShiftMode(array $validated, ?int $branchId, ?array $scopeIds, ShiftService $shifts): array
    {
        $shift = isset($validated['shift']) ? Shift::find($validated['shift']) : null;

        if ($shift && $branchId !== null && $shift->branch_id !== $branchId) {
            $shift = null;
        }

        if ($shift && $scopeIds !== null && ! in_array($shift->branch_id, $scopeIds, true)) {
            $shift = null;
        }

        // Falls back to the branch's own current/most-recent shift both
        // when no id was given at all (the default landing view) and when
        // one was given but just got rejected above — a foreign shift id
        // is dropped, not allowed to leave the page in the "no shift
        // exists" state when the actual branch has a perfectly good one.
        $shift ??= $branchId ? $shifts->mostRecentForBranch($branchId) : null;

        if (! $shift) {
            $latestAnywhere = $branchId === null ? $shifts->mostRecentAcrossBranches($scopeIds) : null;
            $day = $latestAnywhere?->started_at->clone()->timezone('Africa/Accra') ?? Carbon::now('Africa/Accra');

            return [$day->clone()->startOfDay(), $day->clone()->endOfDay(), $day, null];
        }

        $rangeStart = $shift->started_at->clone()->timezone('Africa/Accra');
        $rangeEnd = ($shift->ended_at ?? Carbon::now())->clone()->timezone('Africa/Accra');

        return [$rangeStart, $rangeEnd, $rangeStart->clone(), $shift];
    }
}
