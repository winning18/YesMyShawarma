<?php

namespace App\Services\Reports;

use App\Models\Branch;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a report PDF view in the shared report design (see
 * pdf/_styles and pdf/_letterhead): A4 landscape, the logo, Figtree,
 * and "Page X of Y" stamped on every page. Used by every report export
 * so they can't drift apart.
 */
class ReportPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data  must include 'report' with a 'branch' key
     */
    public function render(string $view, array $data): string
    {
        $branch = $data['report']['branch'] ?? null;

        $dompdf = Pdf::loadView($view, [
            ...$data,
            'address' => $branch ? $this->address($branch) : '',
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

    public function address(Branch $branch): string
    {
        return collect([$branch->address, $branch->ghanapost_code])->filter()->implode(' · ');
    }
}
