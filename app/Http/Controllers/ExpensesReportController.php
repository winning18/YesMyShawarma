<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Reports\ExpenseReportExporter;
use App\Services\Reports\ExpenseReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * "Expenses" tab — every shift expense for a date range on its own,
 * without pulling the whole sales statement. Same access and the same
 * overall-by-default branch handling as Invoices and sales (see
 * ReportsInvoicesController), downloadable in the shared report design.
 */
class ExpensesReportController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function __construct(
        private readonly ExpenseReportService $expenses,
        private readonly ExpenseReportExporter $exporter,
    ) {}

    public function index(Request $request, BranchContext $context): View
    {
        Gate::authorize('reports.view_financial');

        [$from, $to] = $this->resolveRange($request);
        $filters = $this->resolveFilters($request);
        [$branchArgs, $branchViewData] = $this->resolveBranchFilter($request, $context);

        return view('dashboard.reports.expenses', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'expenses' => $this->expenses->paginate($from, $to, $filters, 25, ...$branchArgs),
            'summary' => $this->expenses->summary($from, $to, $filters, ...$branchArgs),
            'recordedByOptions' => $this->expenses->recordedByOptions(...$branchArgs),
            ...$branchViewData,
        ]);
    }

    public function download(Request $request, string $format, BranchContext $context): Response
    {
        Gate::authorize('reports.view_financial');

        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        [$from, $to] = $this->resolveRange($request);
        [$branchArgs] = $this->resolveBranchFilter($request, $context);
        $report = $this->expenses->build($from, $to, $this->resolveFilters($request), ...$branchArgs);
        $filename = $this->exporter->filename($report, $format);

        [$contents, $contentType] = match ($format) {
            'csv' => [$this->exporter->csv($report), 'text/csv; charset=UTF-8'],
            'xlsx' => [$this->exporter->xlsx($report), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pdf' => [$this->exporter->pdf($report), 'application/pdf'],
        };

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, Str::ascii($filename)),
        ]);
    }

    /**
     * Amounts are typed in cedis and converted to pesewas here; a
     * reversed min/max is swapped rather than returning nothing.
     *
     * @return array{search?: string, recorded_by?: int, min_amount?: int, max_amount?: int}
     */
    private function resolveFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'recorded_by' => ['nullable', 'integer', 'exists:users,id'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $filters = [];
        if (filled($validated['search'] ?? null)) {
            $filters['search'] = trim($validated['search']);
        }
        if (isset($validated['recorded_by'])) {
            $filters['recorded_by'] = (int) $validated['recorded_by'];
        }
        if (isset($validated['min_amount'])) {
            $filters['min_amount'] = (int) round($validated['min_amount'] * 100);
        }
        if (isset($validated['max_amount'])) {
            $filters['max_amount'] = (int) round($validated['max_amount'] * 100);
        }
        if (isset($filters['min_amount'], $filters['max_amount']) && $filters['min_amount'] > $filters['max_amount']) {
            [$filters['min_amount'], $filters['max_amount']] = [$filters['max_amount'], $filters['min_amount']];
        }

        return $filters;
    }

    /**
     * Same "tampered input dropped, never trusted" branch-filter
     * resolution as ReportsInvoicesController.
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
