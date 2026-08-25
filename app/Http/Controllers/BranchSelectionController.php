<?php

namespace App\Http\Controllers;

use App\Services\Branches\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchSelectionController extends Controller
{
    public function show(Request $request, BranchContext $context): View
    {
        // Only the Menu Editor's "Branch" link sends ?then=menu — it marks
        // this visit so store() knows to land on the Menu page afterwards
        // instead of Dashboard. Cleared on any other visit (e.g. the
        // guest()-redirect bounce from POS/Reports) so a stale flag from an
        // abandoned earlier click can't hijack an unrelated selection.
        if ($request->query('then') === 'menu') {
            $request->session()->put('branch_select_then', 'menu');
        } else {
            $request->session()->forget('branch_select_then');
        }

        return view('branches.select', [
            'branches' => $context->selectableBranchesFor($request->user()),
        ]);
    }

    public function store(Request $request, BranchContext $context): RedirectResponse
    {
        $availableIds = $context->selectableBranchIdsFor($request->user());

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($availableIds)],
        ]);

        $context->setCurrent((int) $validated['branch_id']);

        if ($request->session()->pull('branch_select_then') === 'menu') {
            return redirect()->route('dashboard.menu-items.index');
        }

        // intended() sends the user back to whatever page redirected them
        // here via redirect()->guest() (POS, or any branch-gated page the
        // ResolveCurrentBranch middleware bounced them from) — Dashboard is
        // only the fallback when nothing specific was intended.
        return Redirect::intended(route('dashboard'));
    }
}
