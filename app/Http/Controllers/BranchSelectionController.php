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
        return view('branches.select', [
            'branches' => $context->branchesFor($request->user()),
        ]);
    }

    public function store(Request $request, BranchContext $context): RedirectResponse
    {
        $availableIds = $context->branchIdsFor($request->user());

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($availableIds)],
        ]);

        $context->setCurrent((int) $validated['branch_id']);

        return Redirect::route('dashboard');
    }
}
