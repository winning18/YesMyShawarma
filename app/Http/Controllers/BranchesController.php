<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function index(): View
    {
        return view('branches.index', [
            'branches' => Branch::where('is_active', true)->get(),
        ]);
    }

    public function select(Branch $branch, CustomerBranchSelection $selection): RedirectResponse
    {
        // The index listing only shows active branches, but that's just a
        // UI filter — nothing stopped this route being hit directly for an
        // inactive branch's id.
        if (! $branch->is_active) {
            return redirect()->route('branches.index')->with('status', 'That branch is no longer available — please choose another.');
        }

        $selection->set($branch->id);

        return redirect()->route('menu.index');
    }
}
