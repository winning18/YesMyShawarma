<?php

namespace App\Http\Controllers;

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
 */
class ReportsInvoicesController extends Controller
{
    public function __construct(private readonly WeeklySalesReportService $weeklySales) {}

    public function index(Request $request): View
    {
        Gate::authorize('reports.view_financial');

        [$weekStart, $weekEnd] = $this->resolveWeek($request);

        $history = $this->weeklySales->weeklyHistory();
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
            'summary' => $this->weeklySales->summary($weekStart, $weekEnd),
            'history' => $paginatedHistory,
            'isThisWeek' => $weekStart->isSameDay(Carbon::now('Africa/Accra')->startOfWeek()),
            'isLastWeek' => $weekStart->isSameDay(Carbon::now('Africa/Accra')->subWeek()->startOfWeek()),
        ]);
    }

    public function download(Request $request, string $format): StreamedResponse|Response
    {
        Gate::authorize('reports.view_financial');

        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        [$weekStart, $weekEnd] = $this->resolveWeek($request);
        $summary = $this->weeklySales->summary($weekStart, $weekEnd);
        $filename = 'sales-'.$weekStart->format('Y-m-d').'-to-'.$weekEnd->format('Y-m-d');

        return match ($format) {
            'csv' => $this->downloadCsv($summary, $filename),
            'xlsx' => $this->downloadXlsx($summary, $filename),
            'pdf' => $this->downloadPdf($summary, $filename),
        };
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
