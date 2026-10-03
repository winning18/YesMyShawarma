<?php

namespace App\Http\Controllers;

use App\Exceptions\ShiftException;
use App\Models\Branch;
use App\Models\Shift;
use App\Services\Branches\BranchContext;
use App\Services\Reports\OrderReportService;
use App\Services\Shifts\ShiftService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function show(Request $request, ShiftService $shifts, OrderReportService $reports): JsonResponse
    {
        $shift = $shifts->activeFor($request->user());

        return response()->json([
            'active' => (bool) $shift,
            'started_at' => $shift?->started_at?->toIso8601String(),
            'branch' => $shift?->branch?->name,
            // Shown in the end-shift modal so whoever's about to end a
            // shift sees the figure they're about to be checked against
            // before they type anything — total_sales is required and
            // validated against this for everyone now, not staff only.
            'system_sales' => $shift ? $this->systemSalesForShift($shift, $reports) : null,
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

    public function end(Request $request, ShiftService $shifts, OrderReportService $reports): JsonResponse
    {
        $shift = $shifts->activeFor($request->user());

        if (! $shift) {
            return response()->json(['message' => 'No open shift to end.'], 422);
        }

        // total_sales is required for everyone who ends a shift now — the
        // old staff-only split lived here as $isStaff; dropped along with
        // it, since there's no longer a role-dependent branch to take.
        $validated = $request->validate([
            'closing_note' => ['nullable', 'string', 'max:255'],
            'total_sales' => ['required', 'numeric', 'min:0'],
            'no_expenses' => ['sometimes', 'boolean'],
            'expenses' => ['array'],
            'expenses.*.description' => ['required_with:expenses', 'string', 'max:255'],
            'expenses.*.amount' => ['required_with:expenses', 'numeric', 'min:0.01'],
        ]);

        $noExpenses = $validated['no_expenses'] ?? false;
        $expenseInputs = $validated['expenses'] ?? [];

        // Mandatory, but not "always a real row" — an explicit "no expenses
        // today" confirmation satisfies it just as well, so a genuinely
        // expense-free shift doesn't need a fake GHS 0.00 line.
        if (! $noExpenses && count($expenseInputs) === 0) {
            return response()->json([
                'message' => __('Add at least one expense, or confirm there were none today.'),
            ], 422);
        }

        $systemSales = $this->systemSalesForShift($shift, $reports);
        $entered = Money::toPesewas($validated['total_sales']);

        // Never allowed to under-report — an amount above system sales is
        // accepted (and recorded, not silently clamped: see
        // shifts.system_sales, this exact figure) so it shows up in the
        // Today report rather than getting lost.
        if ($entered < $systemSales) {
            return response()->json([
                'message' => __(
                    'Total sales cannot be less than this shift\'s recorded sales of GHS :amount.',
                    ['amount' => number_format($systemSales / 100, 2)]
                ),
            ], 422);
        }

        $expenses = collect($expenseInputs)
            ->map(fn (array $expense) => [
                'description' => $expense['description'],
                'amount' => Money::toPesewas($expense['amount']),
            ])
            ->all();

        $shifts->end($shift, $entered, $systemSales, $validated['closing_note'] ?? null, $expenses, $noExpenses);

        return response()->json(['message' => 'Shift ended.']);
    }

    /**
     * Revenue recorded since this specific shift started, not the whole
     * calendar day — schema.md's Shifts section already describes
     * total_sales as "what was actually sold during the shift"; this just
     * makes system_sales (what it's checked against) match that, instead
     * of the previous whole-day figure silently carrying over unchanged
     * from shift to shift when no new orders came in between them. Reuses
     * OrderReportService rather than a second revenue calculation; relies
     * on the same implicit BranchScope as before (not an explicit
     * $branchId) since a shift's own branch_id is always the branch
     * BranchContext is currently resolved to (BranchSelectionController's
     * blockWhileOnShift() guarantees this).
     */
    private function systemSalesForShift(Shift $shift, OrderReportService $reports): int
    {
        $summary = $reports->financialSummary($shift->started_at, now());

        return $summary['revenue_total'];
    }
}
