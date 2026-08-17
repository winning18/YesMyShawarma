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
 * Always today (Africa/Accra), never a date range — see
 * DailySalesReportService for the actual decomposition logic.
 */
class TodayReportController extends Controller
{
    public function index(Request $request, DailySalesReportService $sales, BranchContext $context): View
    {
        Gate::authorize('reports.view_operational');

        $validated = $request->validate([
            'channel' => ['nullable', 'in:web,pos'],
        ]);
        $channel = $validated['channel'] ?? 'pos';

        $today = Carbon::now('Africa/Accra');
        $dayStart = $today->clone()->startOfDay();
        $dayEnd = $today->clone()->endOfDay();

        return view('dashboard.reports.today', [
            'channel' => $channel,
            'today' => $today,
            'summary' => $sales->summary($dayStart, $dayEnd, $channel),
            // Shift model carries no BranchScope (ShiftService::activeFor()
            // needs to see a user's shifts across every branch), so this
            // filters explicitly rather than relying on the global scope
            // every other branch-owned query here gets for free — null
            // branch id (owner's cross-branch aggregate view) means show
            // every branch's shifts, mirroring BranchScope's own no-op
            // behaviour rather than silently returning none.
            'shifts' => Shift::with('user')
                ->when($context->id(), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->whereBetween('started_at', [$dayStart->clone()->utc(), $dayEnd->clone()->utc()])
                ->orderByDesc('started_at')
                ->get(),
        ]);
    }
}
