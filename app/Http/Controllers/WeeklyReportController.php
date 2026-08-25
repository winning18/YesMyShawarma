<?php

namespace App\Http\Controllers;

use App\Services\Reports\WeeklySalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Weekly report" tab — the transaction-level counterpart to "Invoices
 * and sales": one row per order in the chosen week, not one aggregated
 * row per week.
 */
class WeeklyReportController extends Controller
{
    public function __construct(private readonly WeeklySalesReportService $weeklySales) {}

    public function index(Request $request): View
    {
        Gate::authorize('reports.view_financial');

        [$weekStart, $weekEnd] = $this->resolveWeek($request);

        return view('dashboard.reports.weekly', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        Gate::authorize('reports.view_financial');

        [$weekStart, $weekEnd] = $this->resolveWeek($request);
        $orders = $this->weeklySales->detailedOrders($weekStart, $weekEnd);
        $filename = 'weekly-report-'.$weekStart->format('Y-m-d').'-to-'.$weekEnd->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Placed at', 'Customer', 'Phone', 'Status', 'Channel', 'Fulfilment', 'Payment method', 'Total (GHS)']);

            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->reference,
                    $order->placed_at?->timezone('Africa/Accra')->format('Y-m-d H:i'),
                    $order->customer?->name ?? '',
                    $order->customer?->phone ?? '',
                    $order->status,
                    $order->channel,
                    $order->fulfilment_type,
                    $order->payment_method,
                    number_format($order->total / 100, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename);
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
}
