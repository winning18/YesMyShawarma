<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Services\MarketLists\ExpenseKindService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Market list → Manage kinds (ExpenseKindService). Same audience as
 * approving market lists: owner, GM and manager (market_lists.approve).
 */
class ExpenseKindController extends Controller
{
    public function __construct(private readonly ExpenseKindService $kinds) {}

    public function index(Request $request): View
    {
        Gate::authorize('market_lists.approve');

        $matchingFor = $request->session()->get('kind_matches_for');
        $matchingKind = $matchingFor ? ExpenseCategory::find($matchingFor) : null;

        return view('dashboard.market-lists.kinds', [
            'kinds' => ExpenseCategory::orderBy('sort_order')->get(),
            'matchingKind' => $matchingKind,
            'matches' => $matchingKind ? $this->kinds->othersMatching($matchingKind) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('market_lists.approve');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('expense_categories', 'name')],
            'keywords' => ['nullable', 'string', 'max:1000'],
        ]);

        $kind = $this->kinds->create($validated['name'], $this->kinds->parseKeywords($validated['keywords'] ?? null));

        return redirect()->route('dashboard.market-lists.kinds.index')
            ->with('status', __(':kind added.', ['kind' => $kind->name]))
            ->with('kind_matches_for', $kind->id);
    }

    public function update(Request $request, ExpenseCategory $kind): RedirectResponse
    {
        Gate::authorize('market_lists.approve');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('expense_categories', 'name')->ignore($kind->id)],
            'keywords' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->kinds->update($kind, $validated['name'], $this->kinds->parseKeywords($validated['keywords'] ?? null));

        return redirect()->route('dashboard.market-lists.kinds.index')
            ->with('status', __(':kind saved.', ['kind' => $kind->name]))
            ->with('kind_matches_for', $kind->id);
    }

    public function toggle(ExpenseCategory $kind): RedirectResponse
    {
        Gate::authorize('market_lists.approve');

        $this->kinds->setActive($kind, ! $kind->is_active);

        return back()->with('status', $kind->fresh()->is_active
            ? __(':kind is offered again.', ['kind' => $kind->name])
            : __(':kind is hidden. Past purchases keep it.', ['kind' => $kind->name]));
    }

    public function move(Request $request, ExpenseCategory $kind): RedirectResponse
    {
        Gate::authorize('market_lists.approve');

        $this->kinds->move($kind, $request->input('direction') === 'up' ? 'up' : 'down');

        return back();
    }

    public function moveItems(Request $request, ExpenseCategory $kind): RedirectResponse
    {
        Gate::authorize('market_lists.approve');

        $validated = $request->validate(['items' => ['required', 'array'], 'items.*' => ['integer']]);
        $moved = $this->kinds->moveFromOthers($kind, array_map('intval', $validated['items']), $request->user());

        return redirect()->route('dashboard.market-lists.kinds.index')
            ->with('status', trans_choice(':count item moved to :kind.|:count items moved to :kind.', $moved, ['count' => $moved, 'kind' => $kind->name]));
    }
}
