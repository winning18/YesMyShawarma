<?php

namespace App\Http\Controllers;

use App\Exceptions\DamageReportException;
use App\Models\DamageReport;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Models\StockItem;
use App\Services\Branches\BranchContext;
use App\Services\DamageReports\DamageReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The "Damage Reports" sidebar page (index) — a staff member's filing form
 * plus manager/general_manager/owner's review queue, same single-page shape
 * as RefundController (no separate detail/show page). store() and photo()
 * are also reachable by a rider through the shared auth:web,rider route
 * group (routes/web.php) — a rider files against the order they're
 * carrying from their own dashboard's modal, never this page.
 */
class DamageReportController extends Controller
{
    /**
     * Same three-way branch scope as RefundController::index() — owner
     * sees every branch, general_manager sees every branch they hold that
     * role at, manager/staff see their one currently-selected branch.
     */
    public function index(Request $request, BranchContext $context): View
    {
        Gate::authorize('viewAny', DamageReport::class);

        $user = $request->user();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $isGeneralManager = ! $isOwner && $context->hasRoleAtAnyBranch($user, 'general_manager');
        $scopeBranchIds = $isGeneralManager ? $context->branchIdsForRole($user, 'general_manager')->all() : null;

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(DamageReport::STATUSES)],
        ]);

        $reports = DamageReport::query()
            ->when($isOwner, fn ($query) => $query->withoutGlobalScope(BranchScope::class))
            ->when($scopeBranchIds !== null, fn ($query) => $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $scopeBranchIds))
            ->with(['order:id,reference', 'stockItem:id,name,unit', 'reportedBy:id,name', 'reviewedBy:id,name', 'branch:id,name'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.damage-reports.index', [
            'reports' => $reports,
            'status' => $validated['status'] ?? null,
            'statuses' => DamageReport::STATUSES,
            // Only meaningful for the filing form (staff picking which
            // stock item, if any, the damage belongs to) — empty when no
            // branch is resolved (e.g. owner with no branch switched).
            'stockItems' => $context->id()
                ? StockItem::where('branch_id', $context->id())->orderBy('name')->get(['id', 'name', 'unit'])
                : collect(),
        ]);
    }

    public function store(Request $request, DamageReportService $damageReports, BranchContext $context): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'stock_item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'description' => ['required', 'string', 'max:1000'],
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        // withoutGlobalScope: a rider's own session never resolves a branch
        // scope the way staff's does, and the order being reported on is
        // identified by id from the request, not discovered through an
        // already-scoped query.
        $order = isset($validated['order_id'])
            ? Order::withoutGlobalScope(BranchScope::class)->findOrFail($validated['order_id'])
            : null;

        Gate::authorize('create', [DamageReport::class, $order]);

        $user = $request->user();
        $branchId = $order?->branch_id ?? $context->id();
        abort_if(! $branchId, 422, 'No branch selected.');

        $stockItem = isset($validated['stock_item_id']) ? StockItem::findOrFail($validated['stock_item_id']) : null;

        $report = $damageReports->file(
            reporter: $user,
            reporterRole: $context->primaryRoleFor($user, $branchId),
            branchId: $branchId,
            description: $validated['description'],
            photo: $validated['photo'],
            order: $order,
            stockItem: $stockItem,
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => __('Damage report submitted.'), 'id' => $report->id]);
        }

        return back()->with('status', __('Damage report submitted.'));
    }

    public function approve(Request $request, DamageReport $damageReport, DamageReportService $damageReports): RedirectResponse
    {
        Gate::authorize('approve', $damageReport);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $damageReports->approve($damageReport, $request->user(), $validated['note'] ?? null);
        } catch (DamageReportException $e) {
            return back()->withErrors(['damage_report' => $e->getMessage()]);
        }

        return back()->with('status', __('Damage report approved.'));
    }

    public function deny(Request $request, DamageReport $damageReport, DamageReportService $damageReports): RedirectResponse
    {
        Gate::authorize('deny', $damageReport);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $damageReports->deny($damageReport, $request->user(), $validated['note'] ?? null);
        } catch (DamageReportException $e) {
            return back()->withErrors(['damage_report' => $e->getMessage()]);
        }

        return back()->with('status', __('Damage report denied.'));
    }

    public function photo(DamageReport $damageReport, DamageReportService $damageReports): StreamedResponse
    {
        Gate::authorize('viewPhoto', $damageReport);

        abort_if(! $damageReport->photo_path, 404);

        // Only a reviewer's view starts the 24h deletion countdown — the
        // reporter looking at their own submission back (also allowed by
        // viewPhoto above) never counts.
        if (Gate::allows('approve', $damageReport)) {
            $damageReports->markPhotoViewed($damageReport);
        }

        return $damageReports->photoResponse($damageReport);
    }
}
