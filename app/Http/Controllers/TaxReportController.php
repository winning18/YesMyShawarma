<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportBranchFilter;
use App\Services\Reports\TaxReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports → Tax (Express POS step 9, the `tax` module): tax charged per
 * tax line and per day, for filing. Same access and branch handling as
 * the other financial tabs.
 */
class TaxReportController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function __construct(private readonly TaxReportService $report) {}

    public function index(Request $request, ReportBranchFilter $branches): View
    {
        Gate::authorize('reports.view_financial');

        [$from, $to] = $this->resolveRange($request);
        [$branchArgs, $branchViewData] = $branches->resolve($request);

        return view('dashboard.reports.tax', [
            'from' => $from,
            'to' => $to,
            'report' => $this->report->build($from, $to, ...$branchArgs),
            ...$branchViewData,
        ]);
    }

    public function download(Request $request, ReportBranchFilter $branches): StreamedResponse
    {
        Gate::authorize('reports.view_financial');

        [$from, $to] = $this->resolveRange($request);
        [$branchArgs] = $branches->resolve($request);
        $report = $this->report->build($from, $to, ...$branchArgs);
        $money = fn (int $pesewas) => number_format($pesewas / 100, 2, '.', '');

        return response()->streamDownload(function () use ($report, $money) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tax', 'Prices', 'On food', 'On delivery', 'Total'], escape: '');
            foreach ($report['lines'] as $line) {
                fputcsv($out, [$line['label'], $line['inclusive'] ? 'Included' : 'Added', $money($line['food']), $money($line['delivery']), $money($line['total'])], escape: '');
            }
            fputcsv($out, [], escape: '');
            fputcsv($out, ['Sales before tax', $money($report['taxable'])], escape: '');
            fputcsv($out, ['Tax charged', $money($report['tax_total'])], escape: '');
            fputcsv($out, ['Less tax on refunds', $money(-$report['refunded_tax'])], escape: '');
            fputcsv($out, ['Tax due', $money($report['due'])], escape: '');
            fputcsv($out, [], escape: '');
            fputcsv($out, ['Date', 'Orders', 'Sales before tax', 'Tax'], escape: '');
            foreach ($report['days'] as $day) {
                fputcsv($out, [$day['date'], $day['orders'], $money($day['sales']), $money($day['tax'])], escape: '');
            }
            fclose($out);
        }, 'tax-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * This month by default; reversed ranges swapped, over-long ones clamped.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        if (! isset($validated['from']) && ! isset($validated['to'])) {
            $now = Carbon::now('Africa/Accra');

            return [$now->clone()->startOfMonth(), $now->clone()->endOfMonth()];
        }

        $from = Carbon::parse($validated['from'] ?? $validated['to'], 'Africa/Accra')->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $validated['from'], 'Africa/Accra')->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->clone()->startOfDay(), $from->clone()->endOfDay()];
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->clone()->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        }

        return [$from, $to];
    }
}
