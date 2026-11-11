<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders a ProfitReportService::build() array as CSV, XLSX or PDF, in
 * the shared report design (see ExpenseReportExporter). One grid feeds
 * CSV and XLSX so the two can never disagree.
 */
class ProfitReportExporter
{
    public function __construct(private readonly ReportPdfRenderer $pdfRenderer) {}

    public function filename(array $report, string $extension): string
    {
        $name = $report['from']->toDateString().' -- '.$report['to']->toDateString().' '.config('app.name').' Profit Report';

        if (! $report['general'] && $report['branch']) {
            $name .= ' - '.$report['branch']->name;
        }

        return str_replace(['/', '\\'], '-', $name).'.'.$extension;
    }

    public function csv(array $report): string
    {
        $out = fopen('php://temp', 'r+');

        foreach ($this->grid($report) as $row) {
            fputcsv($out, array_map(fn ($cell) => is_array($cell) ? number_format($cell['money'] / 100, 2, '.', '') : $cell, $row));
        }

        rewind($out);
        $contents = stream_get_contents($out);
        fclose($out);

        return $contents;
    }

    public function xlsx(array $report): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($report['from']->toDateString().' - '.$report['to']->toDateString(), 0, 31));

        $rows = [
            [$report['title']],
            [config('app.name').($report['branch'] && ! $report['general'] ? ' — '.$report['branch']->name : ' — All branches')],
            ['Period of report: '.$report['from']->toDateString().' - '.$report['to']->toDateString()],
            [],
            ...$this->grid($report),
        ];

        foreach ($rows as $index => $row) {
            foreach (array_values($row) as $columnIndex => $cell) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).($index + 1);

                if (is_array($cell)) {
                    $sheet->setCellValue($coordinate, $cell['money'] / 100);
                    $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0.00');
                } elseif ($cell !== '' && $cell !== null) {
                    $sheet->setCellValueExplicit($coordinate, (string) $cell, is_int($cell) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
            }

            if (in_array($row[0] ?? null, [$report['title'], 'Period', 'TOTAL', 'Kind'], true)) {
                $sheet->getStyle('A'.($index + 1).':H'.($index + 1))->getFont()->setBold(true);
            }
        }

        for ($i = 1; $i <= 8; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($tempPath);
        $spreadsheet->disconnectWorksheets();
        $contents = file_get_contents($tempPath);
        unlink($tempPath);

        return $contents;
    }

    public function pdf(array $report): string
    {
        return $this->pdfRenderer->render('dashboard.reports.pdf.profit', ['report' => $report]);
    }

    /**
     * Money cells are ['money' => pesewas].
     *
     * @return list<list<mixed>>
     */
    public function grid(array $report): array
    {
        $money = fn (int $pesewas) => ['money' => $pesewas];
        $rows = [['Period', 'Sales (ghs)', 'Refunds (ghs)', 'Purchases & expenses (ghs)', 'Profit (ghs)', 'Bolt Food sales, before commission (ghs)', 'Profit incl. Bolt Food (ghs)']];

        foreach ($report['periods'] as $period) {
            $rows[] = [$period['label'], $money($period['sales']), $money($period['refunds']), $money($period['purchases']), $money($period['profit']), $money($period['bolt_food']), $money($period['profit_with_bolt'])];
        }

        $t = $report['totals'];
        $rows[] = ['TOTAL', $money($t['sales']), $money($t['refunds']), $money($t['purchases']), $money($t['profit']), $money($t['bolt_food']), $money($t['profit_with_bolt'])];
        $rows[] = [];
        $rows[] = ['Kind', 'Purchases', 'Quantity', 'Total (ghs)'];

        foreach ($report['by_kind'] as $kind) {
            $rows[] = [$kind['kind'], $kind['count'], $kind['quantity'] !== null ? rtrim(rtrim(number_format($kind['quantity'], 2, '.', ''), '0'), '.').' '.$kind['unit'] : '', $money($kind['total'])];
        }

        if ($report['by_kind'] === []) {
            $rows[] = ['No approved purchases in this period'];
        }

        if ($report['awaiting']['count'] > 0) {
            $rows[] = [];
            $rows[] = ['Not yet approved, not counted', $report['awaiting']['count'], '', $money($report['awaiting']['total'])];
        }

        return $rows;
    }
}
