<?php

namespace App\Http\Controllers;

use App\Exceptions\MarketListException;
use App\Models\ExpenseCategory;
use App\Models\MarketList;
use App\Models\Scopes\BranchScope;
use App\Services\Branches\BranchContext;
use App\Services\MarketLists\ExpenseKindMatcher;
use App\Services\MarketLists\MarketListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The "Market list" sidebar page: the recording form plus the list of
 * entries — your own, or (for approvers) everything at your branches, with
 * Approve / Send back on pending ones. Same single-page shape as Damage
 * Reports; editing has its own page.
 */
class MarketListController extends Controller
{
    public function __construct(
        private readonly MarketListService $marketLists,
        private readonly BranchContext $context,
        private readonly ExpenseKindMatcher $kinds,
    ) {}

    /**
     * Same three-way scope as DamageReportController::index(), plus: a
     * recorder who can't approve only ever sees their own lists.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MarketList::class);

        $user = $request->user();
        $isOwner = $this->context->hasRoleAtAnyBranch($user, 'owner');
        $isGeneralManager = ! $isOwner && $this->context->hasRoleAtAnyBranch($user, 'general_manager');
        $scopeBranchIds = $isGeneralManager ? $this->context->branchIdsForRole($user, 'general_manager')->all() : null;
        $canApprove = $user->can('market_lists.approve') || $isGeneralManager;

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(MarketList::STATUSES)],
        ]);

        $lists = MarketList::query()
            ->when($isOwner, fn ($query) => $query->withoutGlobalScope(BranchScope::class))
            ->when($scopeBranchIds !== null, fn ($query) => $query->withoutGlobalScope(BranchScope::class)->whereIn('branch_id', $scopeBranchIds))
            ->when(! $canApprove, fn ($query) => $query->where('recorded_by', $user->id))
            ->with(['items.category', 'recordedBy:id,name', 'reviewedBy:id,name', 'branch:id,name'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw('status = ? desc', [MarketList::STATUS_PENDING])
            ->latest('purchased_on')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.market-lists.index', [
            'lists' => $lists,
            'status' => $validated['status'] ?? null,
            'statuses' => MarketList::STATUSES,
            ...$this->formData($request, $lists->getCollection()->flatMap->items->pluck('expense_category_id')->all()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', MarketList::class);

        $validated = $this->validateList($request);
        $branchId = $this->resolveBranchId($request, $validated);

        try {
            $list = $this->marketLists->record(
                recorder: $request->user(),
                branchId: $branchId,
                purchasedOn: $validated['purchased_on'],
                items: $this->items($validated),
                receipt: $request->file('receipt'),
                note: $validated['note'] ?? null,
            );
        } catch (MarketListException $e) {
            return back()->withInput()->withErrors(['market_list' => $e->getMessage()]);
        }

        return redirect()->route('dashboard.market-lists.index')->with('status', $list->status === MarketList::STATUS_APPROVED
            ? __('Market list recorded and approved.')
            : __('Market list submitted for approval.'));
    }

    public function edit(Request $request, MarketList $marketList): View
    {
        Gate::authorize('update', $marketList);

        return view('dashboard.market-lists.edit', [
            'list' => $marketList->load(['items', 'branch:id,name', 'recordedBy:id,name', 'events.actor:id,name']),
            ...$this->formData($request, $marketList->items->pluck('expense_category_id')->all()),
        ]);
    }

    public function update(Request $request, MarketList $marketList): RedirectResponse
    {
        Gate::authorize('update', $marketList);

        $validated = $this->validateList($request, editing: true);

        try {
            $this->marketLists->update(
                list: $marketList,
                editor: $request->user(),
                purchasedOn: $validated['purchased_on'],
                items: $this->items($validated),
                receipt: $request->file('receipt'),
                removeReceipt: $request->boolean('remove_receipt'),
                note: $validated['note'] ?? null,
            );
        } catch (MarketListException $e) {
            return back()->withInput()->withErrors(['market_list' => $e->getMessage()]);
        }

        return redirect()->route('dashboard.market-lists.index')->with('status', __('Market list updated.'));
    }

    public function approve(Request $request, MarketList $marketList): RedirectResponse
    {
        Gate::authorize('approve', $marketList);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'kinds' => ['array'],
            'kinds.*' => ['integer'],
        ]);

        try {
            $this->marketLists->approve($marketList, $request->user(), $validated['note'] ?? null, $validated['kinds'] ?? []);
        } catch (MarketListException $e) {
            return back()->withErrors(['market_list' => $e->getMessage()]);
        }

        return back()->with('status', __('Market list approved.'));
    }

    public function sendBack(Request $request, MarketList $marketList): RedirectResponse
    {
        Gate::authorize('sendBack', $marketList);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->marketLists->sendBack($marketList, $request->user(), $validated['reason']);
        } catch (MarketListException $e) {
            return back()->withErrors(['market_list' => $e->getMessage()]);
        }

        return back()->with('status', __('Sent back for changes.'));
    }

    public function destroy(Request $request, MarketList $marketList): RedirectResponse
    {
        Gate::authorize('delete', $marketList);

        try {
            $this->marketLists->delete($marketList, $request->user());
        } catch (MarketListException $e) {
            return back()->withErrors(['market_list' => $e->getMessage()]);
        }

        return redirect()->route('dashboard.market-lists.index')->with('status', __('Market list deleted.'));
    }

    public function receipt(MarketList $marketList): StreamedResponse
    {
        Gate::authorize('viewReceipt', $marketList);

        return $this->marketLists->receiptResponse($marketList);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateList(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'branch_id' => $editing ? ['prohibited'] : ['nullable', 'integer', 'exists:branches,id'],
            'purchased_on' => ['required', 'date', 'before_or_equal:'.Carbon::now('Africa/Accra')->toDateString(), 'after_or_equal:'.Carbon::now('Africa/Accra')->subDays(60)->toDateString()],
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.expense_category_id' => ['nullable', 'integer'],
            'receipt' => ['nullable', 'image', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Amounts are typed in cedis; stored in pesewas.
     *
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function items(array $validated): array
    {
        return array_values(array_map(fn (array $item) => [
            ...$item,
            'amount' => (int) round(((float) ($item['amount'] ?? 0)) * 100),
        ], $validated['items']));
    }

    /**
     * The current branch, or — for someone with none selected (an owner)
     * — the branch they picked on the form, as long as they can record
     * there.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveBranchId(Request $request, array $validated): int
    {
        $branchId = $this->context->id() ?? (int) ($validated['branch_id'] ?? 0);
        abort_if(! $branchId, 422, 'Choose a branch.');

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($branchId);
        $request->user()->unsetRelation('roles')->unsetRelation('permissions');

        try {
            abort_unless($request->user()->can('market_lists.record'), 403);
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $request->user()->unsetRelation('roles')->unsetRelation('permissions');
        }

        return $branchId;
    }

    /**
     * $usedKindIds: kinds already on the lists being shown. A hidden kind
     * stays selectable for those, or its dropdown would quietly show (and
     * on approval submit) a different kind.
     *
     * @param  list<int>  $usedKindIds
     * @return array<string, mixed>
     */
    private function formData(Request $request, array $usedKindIds = []): array
    {
        $kinds = $this->kinds->categories();
        $hiddenInUse = array_diff(array_unique($usedKindIds), $kinds->pluck('id')->all());

        return [
            'kinds' => $hiddenInUse === [] ? $kinds : $kinds->concat(ExpenseCategory::whereIn('id', $hiddenInUse)->get())->sortBy('sort_order')->values(),
            'currentBranch' => $this->context->branch(),
            'branchOptions' => $this->context->id() ? collect() : $this->context->selectableBranchesFor($request->user())->where('is_active', true)->values(),
            'today' => Carbon::now('Africa/Accra')->toDateString(),
        ];
    }
}
