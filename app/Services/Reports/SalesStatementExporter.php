<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders a SalesStatementService::build() array as CSV, XLSX or PDF,
 * laid out like Bolt Food's partner report: CSV is the bare grid, XLSX
 * adds the recipient header block above it, PDF adds the logo too.
 * CSV and XLSX share one grid() so their rows always line up.
 *
 * Money arrives in pesewas and leaves as cedis with two decimals, a
 * zero shown as "-" in the order and expense tables (as Bolt's does).
 */
class SalesStatementExporter
{
    /**
     * Column the totals-block labels sit in — Bolt's "Order ID" column.
     */
    private const LABEL_COLUMN = 3;

    public function filename(array $report, string $extension): string
    {
        $name = $report['from']->toDateString().' -- '.$report['to']->toDateString()
            .' '.config('app.name').' '.($report['type'] === SalesStatementService::TYPE_BOLT_FOOD ? 'Bolt Food Report' : 'Sales Report');

        if (! $report['general'] && $report['branch']) {
            $name .= ' - '.$report['branch']->name;
        }

        return str_replace(['/', '\\'], '-', $name).'.'.$extension;
    }

    public function csv(array $report): string
    {
        $out = fopen('php://temp', 'r+');

        foreach ($this->grid($report) as $row) {
            fputcsv($out, array_map(fn ($cell) => is_array($cell) ? $this->cellMoney($cell) : $cell, $row));
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
            [$branch ? $this->address($branch) : ''],
            [$branch ? $branch->phone : ''],
            ['Period of report: '.$report['from']->toDateString().' - '.$report['to']->toDateString()],
            [],
        ];

        $rows = [...$header, ...$this->grid($report)];
        $columnCount = count($report['columns']);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $moneyFormat = '#,##0.00;-#,##0.00;"-"';

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            foreach (array_values($row) as $columnIndex => $cell) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).$rowNumber;

                if (is_array($cell)) {
                    $sheet->setCellValue($coordinate, $cell['money'] / 100);
                    $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode(isset($cell['plain']) ? '#,##0.00' : $moneyFormat);
                } elseif ($cell !== '' && $cell !== null) {
                    $sheet->setCellValueExplicit($coordinate, (string) $cell, is_int($cell)
                        ? DataType::TYPE_NUMERIC
                        : DataType::TYPE_STRING);
                }
            }

            $isBold = $index === 0 || $index === 2 || in_array('TOTAL', $row, true)
                || ($index === count($header)) || $this->isSectionHeading($row);
            if ($isBold) {
                $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->getFont()->setBold(true);
            }

            if (array_intersect(['TOTAL', 'Net sales', 'Total Bolt Food sales'], array_filter($row, 'is_string'))) {
                $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
            }
        }

        $sheet->getStyle('A1')->getFont()->setSize(14);

        for ($i = 1; $i <= $columnCount; $i++) {
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
        $dompdf = Pdf::loadView('dashboard.reports.pdf.sales-statement', [
            'report' => $report,
            'address' => $report['branch'] ? $this->address($report['branch']) : '',
            'logoPath' => public_path('images/logo-web.png'),
            'fontDir' => resource_path('fonts/figtree'),
        ])
            ->setPaper('a4', 'landscape')
            ->getDomPDF();

        // "Page X of Y" needs the final page count, which CSS counters
        // can't give DomPDF — stamped onto the canvas after layout.
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 92,
            $canvas->get_height() - 22,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('Figtree'),
            7,
            [0.53, 0.53, 0.53],
        );

        return $dompdf->output();
    }

    /**
     * Shared CSV/XLSX body. Money cells are ['money' => pesewas] so each
     * format can render them its own way (text vs. numeric cell).
     *
     * @return list<list<mixed>>
     */
    public function grid(array $report): array
    {
        $columns = $report['columns'];
        $width = count($columns);
        $blank = array_fill(0, $width, '');
        $totalLabelIndex = collect($columns)->search(fn (array $column) => $column['money']) - 1;
        $rows = [];

        $rows[] = array_map(fn (array $column) => $column['label'].($column['money'] ? ' (ghs)' : ''), $columns);

        foreach ($report['days'] as $day) {
            foreach ($day['rows'] as $orderRow) {
                $rows[] = array_map(fn (array $column) => $column['money'] ? ['money' => $orderRow[$column['key']]] : $orderRow[$column['key']], $columns);
            }

            $rows[] = array_map(function (array $column, int $index) use ($day, $totalLabelIndex) {
                return match (true) {
                    $column['money'] => ['money' => $day['totals'][$column['key']]],
                    $column['key'] === 'date' => $day['date'],
                    $index === $totalLabelIndex => 'TOTAL',
                    default => '',
                };
            }, $columns, array_keys($columns));
            $rows[] = $blank;
        }

        if ($report['days'] === []) {
            $rows[] = $this->labelled($width, 'No orders in this period');
            $rows[] = $blank;
        }

        if ($report['type'] === SalesStatementService::TYPE_SALES) {
            $rows[] = $this->labelled($width, 'EXPENSES');
            $expenseLeading = fn (array $expense) => $report['general']
                ? [$expense['date'], $expense['time'], $expense['branch'], $expense['description']]
                : [$expense['date'], $expense['time'], $expense['description']];
            $labelIndex = $report['general'] ? 3 : 2;

            $rows[] = $this->spread($width, $expenseLeading(['date' => 'Date', 'time' => 'Time', 'branch' => 'Branch', 'description' => 'Description']), 'Amount (ghs)');

            foreach ($report['expenses'] as $expense) {
                $rows[] = $this->spread($width, $expenseLeading($expense), ['money' => $expense['amount']]);
            }

            if ($report['expenses'] === []) {
                $rows[] = $this->spread($width, [$labelIndex => 'No expenses recorded'], ['money' => 0, 'plain' => true]);
            }

            $rows[] = $this->spread($width, [$labelIndex => 'TOTAL'], ['money' => $report['expenses_total'], 'plain' => true]);
            $rows[] = $blank;
        }

        $rows[] = $this->labelled($width, 'TOTALS', 'Total (ghs)');
        foreach ($report['summary_lines'] as $line) {
            $rows[] = $this->labelled($width, $line['label'], ['money' => $line['amount'], 'plain' => true]);
        }
        foreach ($report['memo_lines'] as $line) {
            $rows[] = $this->labelled($width, $line['label'], $line['value']);
        }
        $rows[] = $blank;

        $rows[] = $this->labelled($width, 'ORDERS NOT COUNTED IN SALES', 'Count');
        foreach ($report['excluded_counts'] as $status => $count) {
            $rows[] = $this->labelled($width, $status, $count);
        }

        return $rows;
    }

    public function address($branch): string
    {
        return collect([$branch->address, $branch->ghanapost_code])->filter()->implode(' · ');
    }

    /**
     * A row with $label in the label column and $value in the last one.
     *
     * @return list<mixed>
     */
    private function labelled(int $width, string $label, mixed $value = ''): array
    {
        $row = array_fill(0, $width, '');
        $row[min(self::LABEL_COLUMN, $width - 2)] = $label;
        $row[$width - 1] = $value;

        return $row;
    }

    /**
     * Leading cells from the left, $value in the last column.
     *
     * @param  list<mixed>  $leading
     * @return list<mixed>
     */
    private function spread(int $width, array $leading, mixed $value): array
    {
        $row = array_fill(0, $width, '');
        foreach ($leading as $index => $cell) {
            $row[$index] = $cell;
        }
        $row[$width - 1] = $value;

        return $row;
    }

    private function isSectionHeading(array $row): bool
    {
        return (bool) array_intersect(['EXPENSES', 'TOTALS', 'ORDERS NOT COUNTED IN SALES'], array_filter($row, 'is_string'));
    }

    private function cellMoney(array $cell): string
    {
        $pesewas = $cell['money'];

        return $pesewas === 0 && ! isset($cell['plain']) ? '-' : number_format($pesewas / 100, 2, '.', '');
    }
}
