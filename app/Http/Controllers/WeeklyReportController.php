<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Reports\WeeklySalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Weekly report" tab — the transaction-level counterpart to "Invoices
 * and sales": one row per order in the chosen week, not one aggregated
 * row per week. Defaults to the overall (cross-branch) picture for
 * owner/general_manager, same as the other Reports and invoices pages —
 * see ReportsInvoicesController's resolveBranchFilter() for the shared
 * reasoning.
 */
class WeeklyReportController extends Controller
{
    public function __construct(private readonly WeeklySalesReportService $weeklySales) {}

    public function index(Request $request, BranchContext $context): View
    {
        Gate::authorize('reports.view_financial');

        [$weekStart, $weekEnd] = $this->resolveWeek($request);
        [, $branchViewData] = $this->resolveBranchFilter($request, $context);

        return view('dashboard.reports.weekly', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            ...$branchViewData,
        ]);
    }

    public function download(Request $request, BranchContext $context): StreamedResponse
    {
        Gate::authorize('reports.view_financial');

        [$weekStart, $weekEnd] = $this->resolveWeek($request);
        [$branchArgs] = $this->resolveBranchFilter($request, $context);
        $orders = $this->weeklySales->detailedOrders($weekStart, $weekEnd, ...$branchArgs);
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
     * Same "tampered input dropped, never trusted" branch-filter
     * resolution as ReportsController/ReportsInvoicesController.
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
}
