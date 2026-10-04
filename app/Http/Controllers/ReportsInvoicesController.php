<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Reports\WeeklySalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Invoices and sales" tab — one aggregated row per calendar week
 * (WeeklySalesReportService), downloadable in the format the reference
 * design shows (XLSX/CSV/PDF) for the currently chosen week, plus a
 * per-row quick CSV download in the historical table. VAT is
 * deliberately not shown: nothing in this app tracks a VAT registration
 * or rate today, and fabricating one would misrepresent a real tax
 * figure to whoever downloads it.
 *
 * Defaults to the overall (cross-branch) picture for owner/
 * general_manager, same as Detailed reports/Performance, rather than
 * being silently pinned to whatever branch happens to be ambient in
 * session — see resolveBranchArgs().
 */
class ReportsInvoicesController extends Controller
{
    public function __construct(private readonly WeeklySalesReportService $weeklySales) {}

    public function index(Request $request, BranchContext $context): View
    {
        Gate::authorize('reports.view_financial');

        [$weekStart, $weekEnd] = $this->resolveWeek($request);
        [$branchArgs, $branchViewData] = $this->resolveBranchFilter($request, $context);

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

        return view('dashboard.reports.invoices', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'summary' => $this->weeklySales->summary($weekStart, $weekEnd, ...$branchArgs),
            'history' => $paginatedHistory,
            'isThisWeek' => $weekStart->isSameDay(Carbon::now('Africa/Accra')->startOfWeek()),
            'isLastWeek' => $weekStart->isSameDay(Carbon::now('Africa/Accra')->subWeek()->startOfWeek()),
            ...$branchViewData,
        ]);
    }

    public function download(Request $request, string $format, BranchContext $context): StreamedResponse|Response
    {
        Gate::authorize('reports.view_financial');

        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        [$weekStart, $weekEnd] = $this->resolveWeek($request);
        [$branchArgs] = $this->resolveBranchFilter($request, $context);
        $summary = $this->weeklySales->summary($weekStart, $weekEnd, ...$branchArgs);
        $filename = 'sales-'.$weekStart->format('Y-m-d').'-to-'.$weekEnd->format('Y-m-d');

        return match ($format) {
            'csv' => $this->downloadCsv($summary, $filename),
            'xlsx' => $this->downloadXlsx($summary, $filename),
            'pdf' => $this->downloadPdf($summary, $filename),
        };
    }

    /**
     * Same "tampered input dropped, never trusted" branch-filter
     * resolution as ReportsController/PerformanceController — a manager/
     * staff submitting a branch is ignored (already pinned to their own),
     * a general_manager is limited to branches they oversee, and no
     * filter at all means "overall" for owner/general_manager rather than
     * whatever's ambient in session.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function resolveBranchFilter(Request $request, BranchContext $context): array
    {
        $user = $request->user();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $isGeneralManager = ! $isOwner && $context->hasRoleAtAnyBranch($user, 'general_manager');
        $crossBranch = $isOwner || $isGeneralManager;
        $scopeBranchIds = $isGeneralManager ? $context->branchIdsForRole($user, 'general_manager')->all() : null;

        $validated = $request->validate(['branch' => ['nullable', 'integer', 'exists:branches,id']]);
        $filterBranchId = isset($validated['branch']) ? (int) $validated['branch'] : null;
        if (! $crossBranch || ($isGeneralManager && ! in_array($filterBranchId, $scopeBranchIds, true))) {
            $filterBranchId = null;
        }

        $branchArgs = $filterBranchId !== null
            ? ['branchId' => $filterBranchId]
            : ($crossBranch ? ['ignoreBranchScope' => $isOwner, 'branchIds' => $scopeBranchIds] : []);

        $branchOptionsQuery = Branch::orderBy('name');
        if ($isGeneralManager) {
            $branchOptionsQuery->whereIn('id', $scopeBranchIds);
        }

        return [$branchArgs, [
            'crossBranch' => $crossBranch,
            'branchFilterId' => $filterBranchId,
            'branchOptions' => $crossBranch ? $branchOptionsQuery->get(['id', 'name']) : null,
        ]];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveWeek(Request $request): array
    {
        $validated = $request->validate(['week' => ['nullable', 'date']]);

        $reference = isset($validated['week'])
            ? Carbon::parse($validated['week'], 'Africa/Accra')
            : Carbon::now('Africa/Accra');

        $weekStart = $reference->startOfWeek();

        return [$weekStart, $weekStart->clone()->endOfWeek()];
    }

    /**
     * @return array<int, string|int>
     */
    private function summaryRow(array $summary): array
    {
        return [
            $summary['start']->format('d/m/Y'),
            $summary['end']->format('d/m/Y'),
            $summary['city'],
            $summary['orders_count'],
            number_format($summary['total'] / 100, 2, '.', ''),
            $summary['currency'],
        ];
    }

    private function downloadCsv(array $summary, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($summary) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Start', 'End', 'City', 'Orders', 'Total', 'Currency']);
            fputcsv($out, $this->summaryRow($summary));
            fclose($out);
        }, $filename.'.csv');
    }

    private function downloadXlsx(array $summary, string $filename): Response
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Start', 'End', 'City', 'Orders', 'Total', 'Currency'], null, 'A1');
        $sheet->fromArray($this->summaryRow($summary), null, 'A2');

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($tempPath);

        $contents = file_get_contents($tempPath);
        unlink($tempPath);

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
        ]);
    }

    private function downloadPdf(array $summary, string $filename): Response
    {
        return Pdf::loadView('dashboard.reports.pdf.weekly-sales', ['summary' => $summary])
            ->download($filename.'.pdf');
    }
}
