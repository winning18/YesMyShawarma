<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Branches\WorkingHoursService;
use App\Services\Contact\ContactMessageService;
use App\Services\Customers\CustomerBranchSelection;
use App\Services\Spam\HoneypotGuard;
use App\Services\Spam\TurnstileVerifier;
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
            $branch->todays_hours_label = $workingHours->todayLabel($branch);

            $branch->reviews_count = $branch->approvedReviews()->count();
            $branch->reviews_avg_rating = $branch->approvedReviews()->avg('rating');
            $branch->recent_reviews = $branch->approvedReviews()->latest('moderated_at')->limit(3)->get();
        });

        return view('contact', [
            'branches' => $branches,
            // x-branch-card's "Currently selected" badge — same data source
            // as BranchesController, kept in sync here too.
            'selectedBranchId' => $selection->id(),
        ]);
    }

    public function submit(
        Request $request,
        ContactMessageService $messages,
        HoneypotGuard $honeypot,
        TurnstileVerifier $turnstile,
    ): RedirectResponse {
        // A caught submission looks identical to a real one to the caller —
        // see HoneypotGuard's docblock for why silently pretending success
        // is deliberate rather than a validation error.
        if ($honeypot->isSpam($request)) {
            return back()->with('status', __("Thanks — we've received your message and will get back to you soon."));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['message' => __('Please try submitting the form again.')])->withInput();
        }

        $messages->send(
            $validated['name'],
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
            $validated['message'],
        );

        return back()->with('status', __("Thanks — we've received your message and will get back to you soon."));
    }
}
