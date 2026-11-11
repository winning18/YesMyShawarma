<?php

namespace App\Http\Controllers;

use App\Services\Reports\ProfitReportExporter;
use App\Services\Reports\ProfitReportService;
use App\Services\Reports\ReportBranchFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * "Profit" tab — sales minus approved purchases and expenses per day,
 * week, month or year (ProfitReportService). Same access and branch
 * handling as the other financial tabs.
 */
class ProfitReportController extends Controller
{
    /**
     * Longer than the other tabs' one year, so "per year" can compare years.
     */
    private const MAX_RANGE_DAYS = 1830;

    public function __construct(
        private readonly ProfitReportService $profit,
        private readonly ProfitReportExporter $exporter,
    ) {}

    public function index(Request $request, ReportBranchFilter $branches): View
    {
        Gate::authorize('reports.view_financial');

        [$from, $to, $groupBy] = $this->resolveRange($request);
        [$branchArgs, $branchViewData] = $branches->resolve($request);

        return view('dashboard.reports.profit', [
            'from' => $from,
            'to' => $to,
            'groupBy' => $groupBy,
            'report' => $this->profit->build($from, $to, $groupBy, ...$branchArgs),
            ...$branchViewData,
        ]);
    }

    public function download(Request $request, string $format, ReportBranchFilter $branches): Response
    {
        Gate::authorize('reports.view_financial');

        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        [$from, $to, $groupBy] = $this->resolveRange($request);
        [$branchArgs] = $branches->resolve($request);
        $report = $this->profit->build($from, $to, $groupBy, ...$branchArgs);
        $filename = $this->exporter->filename($report, $format);

        [$contents, $contentType] = match ($format) {
            'csv' => [$this->exporter->csv($report), 'text/csv; charset=UTF-8'],
            'xlsx' => [$this->exporter->xlsx($report), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pdf' => [$this->exporter->pdf($report), 'application/pdf'],
        };

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, Str::ascii($filename)),
        ]);
    }

    /**
     * This month by day by default; reversed ranges swapped, over-long
     * ones clamped.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'group_by' => ['nullable', Rule::in(ProfitReportService::GROUPINGS)],
        ]);

        $groupBy = $validated['group_by'] ?? 'day';

        if (! isset($validated['from']) && ! isset($validated['to'])) {
            $now = Carbon::now('Africa/Accra');

            return [$now->clone()->startOfMonth(), $now->clone()->endOfMonth(), $groupBy];
        }

        $from = Carbon::parse($validated['from'] ?? $validated['to'], 'Africa/Accra')->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $validated['from'], 'Africa/Accra')->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->clone()->startOfDay(), $from->clone()->endOfDay()];
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->clone()->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        }

        return [$from, $to, $groupBy];
    }
}
