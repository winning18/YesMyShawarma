<?php

namespace App\Http\Controllers;

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
    public function index(Request $request, DailySalesReportService $sales): View
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
        ]);
    }
}
