<?php

namespace App\Http\Controllers;

use App\Exceptions\DeliveryFeeCollectionException;
use App\Models\Branch;
use App\Models\DeliveryFeeCollection;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Delivery\DeliveryFeeCollectionService;
use App\Services\Reports\DeliveryFeeReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Reports → Delivery fees → Collections: record a rider handing in their
 * cash delivery fees for a branch and period, list past hand-ins, void a
 * mistake (DeliveryFeeCollectionService). delivery_fees.collect, at the
 * branches the person runs: owner every branch, a GM their GM branches,
 * a manager their current branch.
 */
class DeliveryFeeCollectionController extends Controller
{
    public function __construct(
        private readonly DeliveryFeeCollectionService $collections,
        private readonly DeliveryFeeReportService $fees,
        private readonly BranchContext $context,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('delivery_fees.collect');

        $branches = $this->branchesFor($request->user());
        $validated = $request->validate([
            'branch' => ['nullable', 'integer'],
            'rider' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $branch = $branches->firstWhere('id', (int) ($validated['branch'] ?? 0)) ?? ($branches->count() === 1 ? $branches->first() : null);
        $rider = isset($validated['rider']) ? User::find($validated['rider']) : null;
        $now = Carbon::now('Africa/Accra');
        $from = isset($validated['from']) ? Carbon::parse($validated['from'], 'Africa/Accra')->startOfDay() : $now->clone()->startOfWeek();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'], 'Africa/Accra')->endOfDay() : $now->clone()->endOfWeek();
        if ($from->gt($to)) {
            [$from, $to] = [$to->clone()->startOfDay(), $from->clone()->endOfDay()];
        }

        return view('dashboard.reports.delivery-fee-collections', [
            'branches' => $branches,
            'branch' => $branch,
            'riders' => $branch ? $this->ridersAt($branch) : collect(),
            'rider' => $rider,
            'from' => $from,
            'to' => $to,
            'owed' => $branch && $rider ? $this->collections->owed($rider, $branch->id, $from, $to) : null,
            'handInStartsOn' => $this->fees->handInStartsOn(),
            'history' => DeliveryFeeCollection::withoutGlobalScope(BranchScope::class)
                ->whereIn('branch_id', $branches->pluck('id'))
                ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
                ->when($rider, fn ($query) => $query->where('rider_id', $rider->id))
                ->with(['rider:id,name', 'branch:id,name', 'collectedBy:id,name', 'voidedBy:id,name'])
                ->latest('collected_at')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('delivery_fees.collect');

        $validated = $request->validate([
            'branch' => ['required', 'integer'],
            'rider' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'received' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $branch = $this->branchesFor($request->user())->firstWhere('id', (int) $validated['branch']);
        abort_unless($branch, 403);

        try {
            $collection = $this->collections->record(
                collector: $request->user(),
                rider: User::findOrFail($validated['rider']),
                branchId: $branch->id,
                from: Carbon::parse($validated['from'], 'Africa/Accra')->startOfDay(),
                to: Carbon::parse($validated['to'], 'Africa/Accra')->endOfDay(),
                received: (int) round(((float) $validated['received']) * 100),
                note: $validated['note'] ?? null,
            );
        } catch (DeliveryFeeCollectionException $e) {
            return back()->withErrors(['collection' => $e->getMessage()])->withInput();
        }

        $difference = $collection->difference();
        $message = __('Collection recorded: :count deliveries, GH₵:amount received.', ['count' => $collection->order_count, 'amount' => number_format($collection->received / 100, 2)]);
        if ($difference !== 0) {
            $message .= ' '.($difference < 0
                ? __('Short by GH₵:amount.', ['amount' => number_format(-$difference / 100, 2)])
                : __('Over by GH₵:amount.', ['amount' => number_format($difference / 100, 2)]));
        }

        return redirect()->route('dashboard.reports.delivery-fees.collections.index', ['branch' => $branch->id, 'rider' => $collection->rider_id])
            ->with('status', $message);
    }

    public function void(Request $request, DeliveryFeeCollection $collection): RedirectResponse
    {
        Gate::authorize('delivery_fees.collect');
        abort_unless($this->branchesFor($request->user())->contains('id', $collection->branch_id), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->collections->void($collection, $request->user(), $validated['reason']);
        } catch (DeliveryFeeCollectionException $e) {
            return back()->withErrors(['collection' => $e->getMessage()]);
        }

        return back()->with('status', __('Collection voided. Its deliveries can be collected again.'));
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branchesFor(User $user): Collection
    {
        if ($this->context->hasRoleAtAnyBranch($user, 'owner')) {
            return Branch::where('is_active', true)->orderBy('name')->get();
        }

        $ids = $this->context->hasRoleAtAnyBranch($user, 'general_manager')
            ? $this->context->branchIdsForRole($user, 'general_manager')->all()
            : array_filter([$this->context->id()]);

        return Branch::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Everyone who has delivered for this branch since hand-ins started —
     * not just current rider roles, so a rider who left or was suspended
     * can still be settled with.
     *
     * @return Collection<int, User>
     */
    private function ridersAt(Branch $branch): Collection
    {
        $ids = Order::withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $branch->id)
            ->where('fulfilment_type', 'delivery')
            ->whereNotNull('rider_id')
            ->where('delivered_at', '>=', $this->fees->handInStartsOn()->clone()->utc())
            ->distinct()
            ->pluck('rider_id')
            ->merge($this->context->usersWithRole('rider', $branch->id)->pluck('id'))
            ->unique();

        return User::withTrashed()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }
}
