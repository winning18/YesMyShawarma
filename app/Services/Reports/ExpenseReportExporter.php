<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders an ExpenseReportService::build() array as CSV, XLSX or PDF in
 * the same design as the sales statement (SalesStatementExporter): CSV
 * is the bare grid, XLSX adds the recipient header block, PDF adds the
 * logo via ReportPdfRenderer. Entries are grouped by day with a TOTAL
 * row per day, then the overall totals and any active filters.
 */
class ExpenseReportExporter
{
    public function __construct(private readonly ReportPdfRenderer $pdfRenderer) {}

    public function filename(array $report, string $extension): string
    {
        $name = $report['from']->toDateString().' -- '.$report['to']->toDateString().' '.config('app.name').' Expenses Report';

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

        $branch = $report['branch'];
        $header = [
            [$report['title']],
            ['Recipient'],
            [config('app.name').($branch ? ' — '.$branch->name : '')],
            [$branch ? $this->pdfRenderer->address($branch) : ''],
            [$branch ? $branch->phone : ''],
            ['Period of report: '.$report['from']->toDateString().' - '.$report['to']->toDateString()],
            [],
        ];

        $rows = [...$header, ...$this->grid($report)];
        $width = count($this->columns($report));
        $lastColumn = Coordinate::stringFromColumnIndex($width);

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            foreach (array_values($row) as $columnIndex => $cell) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).$rowNumber;

                if (is_array($cell)) {
                    $sheet->setCellValue($coordinate, $cell['money'] / 100);
                    $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0.00');
                } elseif ($cell !== '' && $cell !== null) {
                    $sheet->setCellValueExplicit($coordinate, (string) $cell, is_int($cell) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
            }

            $strings = array_filter($row, 'is_string');
            if ($index === 0 || $index === 2 || $index === count($header) || array_intersect(['TOTAL', 'TOTALS', 'Total expenses'], $strings)) {
                $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->getFont()->setBold(true);
            }
            if (array_intersect(['TOTAL', 'Total expenses'], $strings)) {
                $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
            }
        }

        $sheet->getStyle('A1')->getFont()->setSize(14);
        for ($i = 1; $i <= $width; $i++) {
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
        return $this->pdfRenderer->render('dashboard.reports.pdf.expenses', [
            'report' => $report,
            'columns' => $this->columns($report),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, money: bool}>
     */
    public function columns(array $report): array
    {
        return array_values(array_filter([
            ['key' => 'date', 'label' => 'Date', 'money' => false],
            ['key' => 'time', 'label' => 'Time', 'money' => false],
            $report['general'] ? ['key' => 'branch', 'label' => 'Branch', 'money' => false] : null,
            ['key' => 'recorded_by', 'label' => 'Recorded by', 'money' => false],
            ['key' => 'description', 'label' => 'Description', 'money' => false],
            ['key' => 'amount', 'label' => 'Amount', 'money' => true],
        ]));
    }

    /**
     * Shared CSV/XLSX body; money cells are ['money' => pesewas].
     *
     * @return list<list<mixed>>
     */
    public function grid(array $report): array
    {
        $columns = $this->columns($report);
        $width = count($columns);
        $blank = array_fill(0, $width, '');
        $labelIndex = $width - 2;
        $line = function (string $label, mixed $value) use ($width, $labelIndex) {
            $row = array_fill(0, $width, '');
            $row[$labelIndex] = $label;
            $row[$width - 1] = $value;

            return $row;
        };

        $rows = [array_map(fn (array $column) => $column['label'].($column['money'] ? ' (ghs)' : ''), $columns)];

        foreach ($report['days'] as $day) {
            foreach ($day['rows'] as $expense) {
                $rows[] = array_map(fn (array $column) => $column['money'] ? ['money' => $expense[$column['key']]] : $expense[$column['key']], $columns);
            }

            $total = $line('TOTAL', ['money' => $day['total']]);
            $total[0] = $day['date'];
            $rows[] = $total;
            $rows[] = $blank;
        }

        if ($report['days'] === []) {
            $rows[] = $line('No expenses in this period', ['money' => 0]);
            $rows[] = $blank;
        }

        $rows[] = $line('TOTALS', 'Total (ghs)');
        $rows[] = $line('Total expenses', ['money' => $report['total']]);
        $rows[] = $line('Entries', $report['count']);
        $rows[] = $line('Shifts with expenses', $report['shifts_count']);

        if ($report['filters'] !== []) {
            $rows[] = $blank;
            $rows[] = $line('Filtered by', implode('; ', $report['filters']));
        }

        return $rows;
    }
}
