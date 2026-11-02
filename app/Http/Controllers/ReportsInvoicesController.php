<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportBranchFilter;
use App\Services\Reports\SalesStatementExporter;
use App\Services\Reports\SalesStatementService;
use App\Services\Reports\WeeklySalesReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * "Invoices and sales" tab — the downloadable sales statement for any
 * date range (SalesStatementService, rendered by SalesStatementExporter
 * as XLSX/CSV/PDF), with a Sales / Bolt Food report switch, plus the
 * historical one-row-per-week table whose quick CSV link downloads that
 * week's statement. VAT is deliberately not shown: nothing in this app
 * tracks a VAT registration or rate today, and fabricating one would
 * misrepresent a real tax figure to whoever downloads it.
 *
 * Defaults to the overall (cross-branch) picture for owner/
 * general_manager, same as Detailed reports/Performance, rather than
 * being silently pinned to whatever branch happens to be ambient in
 * session — see ReportBranchFilter.
 */
class ReportsInvoicesController extends Controller
{
    /**
     * Longest range one statement covers — a year of daily rows is
     * already a long PDF.
     */
    private const MAX_RANGE_DAYS = 366;

    public function __construct(
        private readonly WeeklySalesReportService $weeklySales,
        private readonly SalesStatementService $statements,
        private readonly SalesStatementExporter $exporter,
    ) {}

    public function index(Request $request, ReportBranchFilter $branches): View
    {
        Gate::authorize('reports.view_financial');

        [$from, $to] = $this->resolveRange($request);
        $type = $this->resolveType($request);
        [$branchArgs, $branchViewData] = $branches->resolve($request);

        $history = $this->weeklySales->weeklyHistory(...$branchArgs);
        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));

        $paginatedHistory = new LengthAwarePaginator(
            $history->forPage($page, $perPage)->values(),
            $history->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $now = Carbon::now('Africa/Accra');

        return view('dashboard.reports.invoices', [
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'report' => $this->statements->build($type, $from, $to, ...$branchArgs),
            'history' => $paginatedHistory,
            'isThisWeek' => $from->isSameDay($now->clone()->startOfWeek()) && $to->isSameDay($now->clone()->endOfWeek()),
            'isLastWeek' => $from->isSameDay($now->clone()->subWeek()->startOfWeek()) && $to->isSameDay($now->clone()->subWeek()->endOfWeek()),
            ...$branchViewData,
        ]);
    }

    public function download(Request $request, string $format, ReportBranchFilter $branches): Response
    {
        Gate::authorize('reports.view_financial');

        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        [$from, $to] = $this->resolveRange($request);
        [$branchArgs] = $branches->resolve($request);
        $report = $this->statements->build($this->resolveType($request), $from, $to, ...$branchArgs);

        [$contents, $contentType] = match ($format) {
            'csv' => [$this->exporter->csv($report), 'text/csv; charset=UTF-8'],
            'xlsx' => [$this->exporter->xlsx($report), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pdf' => [$this->exporter->pdf($report), 'application/pdf'],
        };

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $this->exporter->filename($report, $format),
                Str::ascii($this->exporter->filename($report, $format)),
            ),
        ]);
    }

    /**
     * from/to from the date-range picker; a lone ?week= (the history
     * table's per-row links, old bookmarks) means that whole Mon–Sun
     * week; nothing at all means the current week. A reversed range is
     * swapped, an over-long one clamped to MAX_RANGE_DAYS.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'week' => ['nullable', 'date'],
        ]);

        if (! isset($validated['from']) && ! isset($validated['to'])) {
            $reference = isset($validated['week'])
                ? Carbon::parse($validated['week'], 'Africa/Accra')
                : Carbon::now('Africa/Accra');

            return [$reference->clone()->startOfWeek(), $reference->clone()->endOfWeek()];
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

    private function resolveType(Request $request): string
    {
        $validated = $request->validate(['type' => ['nullable', Rule::in(SalesStatementService::TYPES)]]);

        return $validated['type'] ?? SalesStatementService::TYPE_SALES;
    }
}
