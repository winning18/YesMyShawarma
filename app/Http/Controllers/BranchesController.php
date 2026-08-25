<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\WorkingHoursService;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchesController extends Controller
{
    public function index(CustomerBranchSelection $selection, WorkingHoursService $workingHours): View
    {
        $branches = Branch::where('is_active', true)->get()->each(function (Branch $branch) use ($workingHours) {
            // Transient, non-persisted attributes — the "Accepting orders"
            // badge (branches/index, contact) needs the actual open/closed
            // state, not just the manual is_accepting_orders switch, so
            // customers aren't told a closed branch is "accepting orders"
            // just because nobody's paused it.
            $branch->is_open_now = $workingHours->isOpenNow($branch);
            $branch->next_opening_label = $branch->is_open_now ? null : $workingHours->nextOpening($branch)?->format('l g:ia');
            $branch->todays_hours_label = $workingHours->todayLabel($branch);
        });

        return view('branches.index', [
            'branches' => $branches,
            'selectedBranchId' => $selection->id(),
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
