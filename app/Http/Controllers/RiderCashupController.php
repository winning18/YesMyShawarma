<?php

namespace App\Http\Controllers;

use App\Exceptions\RiderCashupException;
use App\Models\Branch;
use App\Models\Order;
use App\Models\RiderCashup;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Delivery\RiderCashupService;
use App\Services\Reports\DeliveryFeeReportService;
use App\Services\Shifts\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Rider cash-ups: record a rider handing in what they must account for
 * (food money, and fees when the business has them handed in) at this
 * device's open till, list past cash-ups, void a mistake
 * (RiderCashupService). delivery_fees.collect, at the branches the person
 * runs: owner every branch, a GM their GM branches, a manager or cashier
 * their current branch.
 */
class RiderCashupController extends Controller
{
    public function __construct(
        private readonly RiderCashupService $cashups,
        private readonly DeliveryFeeReportService $fees,
        private readonly BranchContext $context,
        private readonly ShiftService $shifts,
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
        // Everything not yet handed in, by default.
        $from = isset($validated['from']) ? Carbon::parse($validated['from'], 'Africa/Accra')->startOfDay() : $this->fees->handInStartsOn()->clone();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'], 'Africa/Accra')->endOfDay() : $now->clone()->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->clone()->startOfDay(), $from->clone()->endOfDay()];
        }

        return view('dashboard.reports.rider-cashups', [
            'branches' => $branches,
            'branch' => $branch,
            'riders' => $branch ? $this->ridersAt($branch) : collect(),
            'rider' => $rider,
            'from' => $from,
            'to' => $to,
            'owed' => $branch && $rider ? $this->cashups->owed($rider, $branch->id, $from, $to) : null,
            'handInStartsOn' => $this->fees->handInStartsOn(),
            'feesHandedIn' => $this->fees->feesHandedIn(),
            'till' => $branch ? $this->shifts->activeForBranch($branch->id) : null,
            'history' => RiderCashup::withoutGlobalScope(BranchScope::class)
                ->whereIn('branch_id', $branches->pluck('id'))
                ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
                ->when($rider, fn ($query) => $query->where('rider_id', $rider->id))
                ->with(['rider:id,name', 'branch:id,name', 'collectedBy:id,name', 'voidedBy:id,name', 'shift.terminal:id,name'])
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
            // 'out': the business paid the rider (fees it held for them came to more than they handed in).
            'direction' => ['nullable', 'in:in,out'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $branch = $this->branchesFor($request->user())->firstWhere('id', (int) $validated['branch']);
        abort_unless($branch, 403);

        try {
            $cashup = $this->cashups->record(
                collector: $request->user(),
                rider: User::findOrFail($validated['rider']),
                branchId: $branch->id,
                from: Carbon::parse($validated['from'], 'Africa/Accra')->startOfDay(),
                to: Carbon::parse($validated['to'], 'Africa/Accra')->endOfDay(),
                received: (($validated['direction'] ?? 'in') === 'out' ? -1 : 1) * (int) round(((float) $validated['received']) * 100),
                note: $validated['note'] ?? null,
                till: $this->shifts->activeForBranch($branch->id),
            );
        } catch (RiderCashupException $e) {
            return back()->withErrors(['collection' => $e->getMessage()])->withInput();
        }

        $difference = $cashup->difference();
        $message = $cashup->received < 0
            ? __('Cash-up recorded: :count deliveries, GH₵:amount paid to the rider.', ['count' => $cashup->order_count, 'amount' => number_format(-$cashup->received / 100, 2)])
            : __('Cash-up recorded: :count deliveries, GH₵:amount received.', ['count' => $cashup->order_count, 'amount' => number_format($cashup->received / 100, 2)]);
        if ($difference !== 0) {
            $message .= ' '.($difference < 0
                ? __('Short by GH₵:amount.', ['amount' => number_format(-$difference / 100, 2)])
                : __('Over by GH₵:amount.', ['amount' => number_format($difference / 100, 2)]));
        }

        if ($cashup->shift_id === null) {
            $message .= ' '.__('No till is open on this device, so it isn\'t counted in any till.');
        }

        return redirect()->route('dashboard.rider-cashups.index', ['branch' => $branch->id, 'rider' => $cashup->rider_id])
            ->with('status', $message);
    }

    public function void(Request $request, RiderCashup $cashup): RedirectResponse
    {
        Gate::authorize('delivery_fees.collect');
        abort_unless($this->branchesFor($request->user())->contains('id', $cashup->branch_id), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->cashups->void($cashup, $request->user(), $validated['reason']);
        } catch (RiderCashupException $e) {
            return back()->withErrors(['collection' => $e->getMessage()]);
        }

        return back()->with('status', __('Cash-up voided. Its deliveries can be handed in again.'));
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
