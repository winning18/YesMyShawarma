<?php

namespace App\Http\Controllers;

use App\Exceptions\CounterException;
use App\Models\Printer;
use App\Models\Terminal;
use App\Services\Branches\BranchContext;
use App\Services\Shifts\CounterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Counters (Express POS step 7a): the current branch's
 * counters, and setting this device up as one. Owner, general manager
 * and manager (settings.manage).
 */
class CounterController extends Controller
{
    public function index(BranchContext $context, CounterService $counters): View|RedirectResponse
    {
        Gate::authorize('settings.manage');
        $branch = $context->branch();
        // Printers and counters belong to one branch. An owner viewing
        // every branch picks one first, then comes back here (as the POS does).
        if (! $branch) {
            return redirect()->guest(route('branches.select'))->with('status', __('Select a branch to set up its counters.'));
        }

        return view('dashboard.counters.index', [
            'branch' => $branch,
            'counters' => Terminal::where('branch_id', $branch->id)->orderBy('name')->get()
                ->map(fn (Terminal $counter) => [
                    'counter' => $counter,
                    'till' => $counters->openTill($counter),
                ]),
            'thisDevice' => $counters->current(),
            'receiptPrinters' => Printer::where('branch_id', $branch->id)->where('role', Printer::RECEIPT)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, BranchContext $context, CounterService $counters): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $branch = $context->branch() ?? abort(404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('terminals', 'name')->where('branch_id', $branch->id)],
        ]);

        try {
            $counters->create($branch, $validated['name']);
        } catch (CounterException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return back()->with('status', __('Counter added.'));
    }

    public function update(Request $request, Terminal $counter, CounterService $counters): RedirectResponse
    {
        $this->authorizeCounter($counter);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('terminals', 'name')->where('branch_id', $counter->branch_id)->ignore($counter->id)],
            'is_active' => ['nullable', 'boolean'],
            'receipt_printer_id' => ['nullable', 'integer', Rule::exists('printers', 'id')->where('branch_id', $counter->branch_id)->where('role', 'receipt')],
        ]);

        try {
            $counters->update($counter, $validated['name'], $request->boolean('is_active'), $validated['receipt_printer_id'] ?? null);
        } catch (CounterException $e) {
            return back()->withErrors(['counter' => $e->getMessage()]);
        }

        return back()->with('status', __('Counter saved.'));
    }

    /** This device becomes the counter. */
    public function pair(Terminal $counter, CounterService $counters): RedirectResponse
    {
        $this->authorizeCounter($counter);
        abort_unless($counter->is_active, 422);

        return back()->with('status', __('This device is now :counter.', ['counter' => $counter->name]))
            ->withCookie($counters->pair($counter));
    }

    /** The counter forgets whichever device it had. */
    public function unpair(Terminal $counter, CounterService $counters): RedirectResponse
    {
        $this->authorizeCounter($counter);
        $counters->unpair($counter);

        return back()->with('status', __(':counter has no device now.', ['counter' => $counter->name]));
    }

    /** This device stops being a counter. */
    public function forget(CounterService $counters): RedirectResponse
    {
        Gate::authorize('settings.manage');

        return back()->with('status', __('This device is no longer a counter.'))
            ->withCookie($counters->forgetThisDevice());
    }

    private function authorizeCounter(Terminal $counter): void
    {
        Gate::authorize('settings.manage');
        abort_unless((int) $counter->branch_id === (int) app(BranchContext::class)->id(), 404);
    }
}
