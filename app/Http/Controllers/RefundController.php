<?php

namespace App\Http\Controllers;

use App\Exceptions\RefundException;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Services\Branches\BranchContext;
use App\Services\Orders\RefundService;
use App\Services\Shifts\ShiftService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The "Refunds" sidebar page (index) and the request/approve/deny/complete
 * actions reached from an order's detail page. owner/manager/general_manager
 * all hold identical refund rights (payments.md) — the only scope
 * difference between them is *which branches'* refunds index() shows,
 * same three-way split as PerformanceController: owner sees every branch,
 * general_manager sees every branch they hold that role at, manager sees
 * their one currently-selected branch. See RefundService's docblock for
 * why none of this ever touches orders.status.
 */
class RefundController extends Controller
{
    /**
     * @var array<string, int>
     */
    private const RANGE_DAYS = ['today' => 1, '7' => 7, '30' => 30];

    public function index(Request $request, BranchContext $context): View
    {
        Gate::authorize('viewAny', Refund::class);

        $user = $request->user();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $isGeneralManager = ! $isOwner && $context->hasRoleAtAnyBranch($user, 'general_manager');
        $scopeBranchIds = $isGeneralManager ? $context->branchIdsForRole($user, 'general_manager')->all() : null;

        $validated = $request->validate([
            'channel' => ['nullable', 'in:web,pos'],
            'status' => ['nullable', Rule::in(Refund::STATUSES)],
            'range' => ['nullable', 'in:today,7,30'],
        ]);

        $rangeKey = $validated['range'] ?? '30';
        $days = self::RANGE_DAYS[$rangeKey];
        $to = Carbon::now('Africa/Accra')->endOfDay();
        $from = $to->clone()->subDays($days - 1)->startOfDay();

        // Cross-branch (owner/general_manager) means the *nested* order
        // relation needs its own BranchScope bypass too — Order carries
        // its own global scope independent of Refund's, so a refund whose
        // order sits at a branch outside the viewer's own ambient session
        // branch would otherwise eager-load order as null (and, in
        // whereHas below, be silently excluded from a channel filter).
        $crossBranch = $isOwner || $scopeBranchIds !== null;

        $refunds = Refund::query()
            ->when($isOwner, fn ($query) => $query->withoutGlobalScope(BranchScope::class))
            ->when($scopeBranchIds !== null, fn ($query) => $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $scopeBranchIds))
            ->with(['order' => function ($query) use ($crossBranch) {
                $query->when($crossBranch, fn ($q) => $q->withoutGlobalScope(BranchScope::class))
                    ->select(['id', 'reference', 'channel', 'total', 'branch_id']);
            }, 'requestedBy', 'reviewedBy', 'completedBy', 'branch'])
            ->whereBetween('created_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->when($validated['channel'] ?? null, fn ($query, $channel) => $query->whereHas(
                'order',
                fn ($q) => $q->when($crossBranch, fn ($q) => $q->withoutGlobalScope(BranchScope::class))->where('channel', $channel)
            ))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.refunds.index', [
            'refunds' => $refunds,
            'channel' => $validated['channel'] ?? null,
            'status' => $validated['status'] ?? null,
            'rangeKey' => $rangeKey,
            'statuses' => Refund::STATUSES,
        ]);
    }

    public function store(Request $request, Order $order, RefundService $refunds, BranchContext $context, ShiftService $shifts): RedirectResponse
    {
        Gate::authorize('requestRefund', $order);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $amount = Money::toPesewas($validated['amount']);
        $actorType = $context->primaryRoleFor($user, $order->branch_id);
        $shiftId = $shifts->activeFor($user)?->id;

        try {
            $refund = Gate::allows('orders.refund')
                ? $refunds->directRefund($order, $user, $amount, $validated['reason'], $actorType, $shiftId)
                : $refunds->request($order, $user, $amount, $validated['reason'], $actorType, $shiftId);
        } catch (RefundException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $message = $refund->status === Refund::STATUS_COMPLETED
            ? __('Refund processed.')
            : __('Refund requested — awaiting approval.');

        return back()->with('status', $message);
    }

    public function approve(Request $request, Refund $refund, RefundService $refunds): RedirectResponse
    {
        Gate::authorize('approve', $refund);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $refunds->approve($refund, $request->user(), $validated['note'] ?? null);
        } catch (RefundException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('status', __('Refund approved — staff can now complete it.'));
    }

    public function deny(Request $request, Refund $refund, RefundService $refunds): RedirectResponse
    {
        Gate::authorize('deny', $refund);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $refunds->deny($refund, $request->user(), $validated['note'] ?? null);
        } catch (RefundException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('status', __('Refund request denied.'));
    }

    public function complete(Request $request, Refund $refund, RefundService $refunds, BranchContext $context, ShiftService $shifts): RedirectResponse
    {
        Gate::authorize('complete', $refund);

        $user = $request->user();
        $actorType = $context->primaryRoleFor($user, $refund->branch_id);

        try {
            $refunds->complete($refund, $user, $actorType, $shifts->activeFor($user)?->id);
        } catch (RefundException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('status', __('Refund processed.'));
    }
}
