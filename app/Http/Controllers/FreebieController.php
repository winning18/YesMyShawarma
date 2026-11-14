<?php

namespace App\Http\Controllers;

use App\Exceptions\FreebieException;
use App\Models\ComplimentaryOrder;
use App\Services\Branches\BranchContext;
use App\Services\Freebies\FreebieService;
use App\Services\Reports\FreebieReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Freebies & rewards": every freebie with approve/reject for the people
 * allowed to, and the WhatsApp group reward totals (owner, 2026-11-14).
 * freebies.approve holders; branch scope like the market list — owner
 * every branch, a GM their GM branches, a manager their current branch.
 */
class FreebieController extends Controller
{
    public function __construct(
        private readonly FreebieService $freebies,
        private readonly FreebieReportService $report,
        private readonly BranchContext $context,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('freebies.approve');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(ComplimentaryOrder::STATUSES)],
        ]);

        $now = Carbon::now('Africa/Accra');
        $from = isset($validated['from']) ? Carbon::parse($validated['from'], 'Africa/Accra')->startOfDay() : $now->clone()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'], 'Africa/Accra')->endOfDay() : $now->clone()->endOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to->clone()->startOfDay(), $from->clone()->endOfDay()];
        }

        $branchIds = $this->branchIdsFor($request);
        $all = $this->report->freebies($from, $to, $branchIds);
        $status = $validated['status'] ?? null;

        return view('dashboard.freebies.index', [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'freebies' => $status ? $all->where('status', $status)->values() : $all,
            'summary' => $this->report->summary($all),
            'whatsapp' => $this->report->whatsapp($from, $to, $branchIds),
        ]);
    }

    public function approve(Request $request, ComplimentaryOrder $freebie): RedirectResponse
    {
        Gate::authorize('review', $freebie);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->freebies->approve($freebie, $request->user(), $validated['note'] ?? null);
        } catch (FreebieException $e) {
            return back()->withErrors(['freebie' => $e->getMessage()]);
        }

        return back()->with('status', __('Freebie approved.'));
    }

    public function reject(Request $request, ComplimentaryOrder $freebie): RedirectResponse
    {
        Gate::authorize('review', $freebie);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->freebies->reject($freebie, $request->user(), $validated['reason']);
        } catch (FreebieException $e) {
            return back()->withErrors(['freebie' => $e->getMessage()]);
        }

        return back()->with('status', __('Freebie rejected and flagged against whoever gave it.'));
    }

    /**
     * @return list<int>|null null = every branch (owner)
     */
    private function branchIdsFor(Request $request): ?array
    {
        $user = $request->user();

        if ($this->context->hasRoleAtAnyBranch($user, 'owner')) {
            return null;
        }

        if ($this->context->hasRoleAtAnyBranch($user, 'general_manager')) {
            return $this->context->branchIdsForRole($user, 'general_manager')->all();
        }

        return array_values(array_filter([$this->context->id()]));
    }
}
