<?php

namespace App\Http\Controllers;

use App\Services\Reports\DeliveryFeeReportService;
use App\Services\Reports\ReportBranchFilter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Delivery fees" tab — every delivery fee riders brought in for a date
 * range: totals, one line per rider, and each delivered order behind
 * them. Built by DeliveryFeeReportService, the same service behind the
 * rider app's own totals, so the two always agree. Same access and branch
 * handling as Expenses (ExpensesReportController).
 */
class DeliveryFeesReportController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public const COLLECTION_LABELS = [
        DeliveryFeeReportService::STATUS_KEPT => 'Kept by rider',
        DeliveryFeeReportService::STATUS_PREPAID => 'Prepaid',
        DeliveryFeeReportService::STATUS_COLLECTED => 'Collected',
        DeliveryFeeReportService::STATUS_TO_COLLECT => 'To collect',
    ];

    public function __construct(private readonly DeliveryFeeReportService $fees) {}

    public function index(Request $request, ReportBranchFilter $branches): View
    {
        Gate::authorize('reports.view_financial');

        [$from, $to] = $this->resolveRange($request);
        [$branchArgs, $branchViewData] = $branches->resolve($request);
        $riderId = $this->resolveRider($request);

        $collection = $this->resolveCollectionFilter($request);
        $allRows = $this->fees->rows($from, $to, null, ...$branchArgs);
        $rows = $riderId !== null ? $allRows->where('rider_id', $riderId)->values() : $allRows;
        $rows = $collection !== null ? $rows->where('collection_status', $collection)->values() : $rows;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('dashboard.reports.delivery-fees', [
            'from' => $from,
            'to' => $to,
            'riderId' => $riderId,
            'collection' => $collection,
            'summary' => $this->fees->summary($allRows),
            'riders' => $this->fees->byRider($allRows),
            'riderSummary' => $riderId !== null ? $this->fees->summary($rows) : null,
            'orders' => (new LengthAwarePaginator($rows->forPage($page, 50)->values(), $rows->count(), 50, $page, [
                'path' => $request->url(),
            ]))->withQueryString(),
            ...$branchViewData,
        ]);
    }

    public function download(Request $request, ReportBranchFilter $branches): StreamedResponse
    {
        Gate::authorize('reports.view_financial');

        [$from, $to] = $this->resolveRange($request);
        [$branchArgs] = $branches->resolve($request);
        $rows = $this->fees->rows($from, $to, $this->resolveRider($request), ...$branchArgs);
        $collection = $this->resolveCollectionFilter($request);
        $rows = $collection !== null ? $rows->where('collection_status', $collection)->values() : $rows;

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Delivered', 'Order', 'Branch', 'Rider', 'Area', 'Payment', 'Delivery fee (GHS)', 'Cash from customer (GHS)', 'Prepaid (GHS)', 'Fee note', 'Collection', 'Collected at', 'Collected by']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['delivered_at']->format('Y-m-d H:i'),
                    $row['reference'],
                    $row['branch'],
                    $row['rider'],
                    $row['area'],
                    $row['payment_method'],
                    number_format($row['fee'] / 100, 2, '.', ''),
                    number_format($row['cash'] / 100, 2, '.', ''),
                    number_format($row['prepaid'] / 100, 2, '.', ''),
                    $row['fee_note'],
                    self::COLLECTION_LABELS[$row['collection_status']],
                    $row['collected_at']?->format('Y-m-d H:i'),
                    $row['collected_by'],
                ]);
            }

            fclose($out);
        }, 'delivery-fees-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 'to_collect' or 'collected' narrows the list; anything else is all.
     */
    private function resolveCollectionFilter(Request $request): ?string
    {
        $value = $request->query('collection');

        return in_array($value, [DeliveryFeeReportService::STATUS_TO_COLLECT, DeliveryFeeReportService::STATUS_COLLECTED], true) ? $value : null;
    }

    private function resolveRider(Request $request): ?int
    {
        $validated = $request->validate(['rider' => ['nullable', 'integer']]);

        return isset($validated['rider']) ? (int) $validated['rider'] : null;
    }

    /**
     * Same as ReportsInvoicesController: from/to from the date-range
     * picker, the current week by default, reversed ranges swapped and
     * over-long ones clamped.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if (! isset($validated['from']) && ! isset($validated['to'])) {
            $now = Carbon::now('Africa/Accra');

            return [$now->clone()->startOfWeek(), $now->clone()->endOfWeek()];
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
