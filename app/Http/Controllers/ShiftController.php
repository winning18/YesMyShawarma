<?php

namespace App\Http\Controllers;

use App\Exceptions\ShiftException;
use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Reports\OrderReportService;
use App\Services\Shifts\ShiftService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShiftController extends Controller
{
    public function show(Request $request, ShiftService $shifts, BranchContext $context, OrderReportService $reports): JsonResponse
    {
        $user = $request->user();
        $shift = $shifts->activeFor($user);
        $isStaff = $shift && $context->primaryRoleFor($user, $shift->branch_id) === 'staff';

        return response()->json([
            'active' => (bool) $shift,
            'started_at' => $shift?->started_at?->toIso8601String(),
            'branch' => $shift?->branch?->name,
            // Shown in the end-shift modal so staff see the figure they're
            // about to be checked against before they type anything —
            // never computed for other roles, who aren't validated against
            // it at all.
            'system_sales' => $isStaff ? $this->todaysSystemSales($reports) : null,
        ]);
    }

    public function start(Request $request, ShiftService $shifts, BranchContext $context): JsonResponse
    {
        $branchId = $context->id();

        if (! $branchId) {
            return response()->json(['message' => 'Select a branch before starting a shift.'], 422);
        }

        $validated = $request->validate([
            'opening_note' => ['nullable', 'string', 'max:255'],
            // Optional for every role — the "staff must be forced through
            // this before reaching the dashboard" requirement is about the
            // popup appearing at all, not about this field being filled.
            'starting_cash' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $shifts->start(
                $request->user(),
                Branch::findOrFail($branchId),
                isset($validated['starting_cash']) ? Money::toPesewas($validated['starting_cash']) : null,
                $validated['opening_note'] ?? null,
            );
        } catch (ShiftException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Shift started.']);
    }

    public function end(Request $request, ShiftService $shifts, BranchContext $context, OrderReportService $reports): JsonResponse
    {
        $shift = $shifts->activeFor($request->user());

        if (! $shift) {
            return response()->json(['message' => 'No open shift to end.'], 422);
        }

        // Required specifically for staff — manager/owner/rider keep the
        // original optional-note-only end flow.
        $isStaff = $context->primaryRoleFor($request->user(), $shift->branch_id) === 'staff';

        $validated = $request->validate([
            'closing_note' => ['nullable', 'string', 'max:255'],
            'total_sales' => [$isStaff ? 'required' : 'nullable', 'numeric', 'min:0'],
        ]);

        $systemSales = null;

        if ($isStaff) {
            $systemSales = $this->todaysSystemSales($reports);
            $entered = Money::toPesewas($validated['total_sales']);

            // Never allowed to under-report — an amount above system sales
            // is accepted (and recorded, not silently clamped: see
            // shifts.system_sales, this exact figure) so it shows up in
            // the Today report rather than getting lost.
            if ($entered < $systemSales) {
                return response()->json([
                    'message' => __(
                        'Total sales cannot be less than today\'s recorded sales of GHS :amount.',
                        ['amount' => number_format($systemSales / 100, 2)]
                    ),
                ], 422);
            }
        }

        $shifts->end(
            $shift,
            isset($validated['total_sales']) ? Money::toPesewas($validated['total_sales']) : null,
            $systemSales,
            $validated['closing_note'] ?? null,
        );

        return response()->json(['message' => 'Shift ended.']);
    }

    /**
     * Whole calendar day (Africa/Accra), any shift, any channel — matches
     * TodayReportController's own definition of "today's sales" exactly,
     * reusing OrderReportService rather than a second revenue calculation.
     * A moving target while the day is still in progress: two shifts
     * ending hours apart on the same day will see different figures here,
     * each accurate as of its own moment — that's why it gets snapshotted
     * onto the shift row rather than recomputed later.
     */
    private function todaysSystemSales(OrderReportService $reports): int
    {
        $today = Carbon::now('Africa/Accra');

        $summary = $reports->financialSummary(
            $today->clone()->startOfDay()->utc(),
            $today->clone()->endOfDay()->utc(),
        );

        return $summary['revenue_total'];
    }
}
