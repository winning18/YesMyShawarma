<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\WorkingHoursService;
use App\Services\Contact\ContactMessageService;
use App\Services\Customers\CustomerBranchSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(WorkingHoursService $workingHours, CustomerBranchSelection $selection): View
    {
        $branches = Branch::where('is_active', true)->get()->each(function (Branch $branch) use ($workingHours) {
            // See BranchesController::index() — same reasoning.
            $branch->is_open_now = $workingHours->isOpenNow($branch);
            $branch->next_opening_label = $branch->is_open_now ? null : $workingHours->nextOpening($branch)?->format('l g:ia');
        });

        return view('contact', [
            'branches' => $branches,
            // x-branch-card's "Currently selected" badge — same data source
            // as BranchesController, kept in sync here too.
            'selectedBranchId' => $selection->id(),
        ]);
    }

    public function submit(Request $request, ContactMessageService $messages): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $messages->send(
            $validated['name'],
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
            $validated['message'],
        );

        return back()->with('status', __("Thanks — we've received your message and will get back to you soon."));
    }
}
