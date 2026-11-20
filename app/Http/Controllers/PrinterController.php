<?php

namespace App\Http\Controllers;

use App\Models\PrintAgent;
use App\Models\Printer;
use App\Models\Terminal;
use App\Services\Branches\BranchContext;
use App\Services\Printing\PrintAgentService;
use App\Services\Printing\PrinterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Printers (Express POS step 8a): the current branch's receipt
 * and kitchen printers. Owner, general manager and manager
 * (settings.manage).
 */
class PrinterController extends Controller
{
    public function index(BranchContext $context): View
    {
        Gate::authorize('settings.manage');
        $branch = $context->branch() ?? abort(404);

        return view('dashboard.printers.index', [
            'branch' => $branch,
            'printers' => Printer::where('branch_id', $branch->id)->orderBy('role')->orderBy('name')->get(),
            'counters' => Terminal::where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get(),
            'agents' => PrintAgent::where('branch_id', $branch->id)->orderBy('name')->get(),
        ]);
    }

    /**
     * A one-time code for connecting a print agent (step 8b), shown once.
     * The same agent asking again (reinstalled PC) gets a fresh code.
     */
    public function connectAgent(Request $request, BranchContext $context, PrintAgentService $agents): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $branch = $context->branch() ?? abort(404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'agent_id' => ['nullable', 'integer', Rule::exists('print_agents', 'id')->where('branch_id', $branch->id)],
        ]);

        [$agent, $code] = $agents->startConnecting($branch, $validated['name'], isset($validated['agent_id']) ? PrintAgent::find($validated['agent_id']) : null);

        return back()->with('agentCode', ['agent' => $agent->name, 'code' => $code, 'minutes' => PrintAgentService::CODE_MINUTES]);
    }

    public function disconnectAgent(PrintAgent $agent, PrintAgentService $agents): RedirectResponse
    {
        Gate::authorize('settings.manage');
        abort_unless($agent->branch_id === (int) app(BranchContext::class)->id(), 404);

        $agents->disconnect($agent);

        return back()->with('status', __(':name is disconnected and can no longer print.', ['name' => $agent->name]));
    }

    public function store(Request $request, BranchContext $context, PrinterService $printers): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $branch = $context->branch() ?? abort(404);

        $printers->save($branch, $this->validated($request, $branch->id));

        return back()->with('status', __('Printer added.'));
    }

    public function update(Request $request, Printer $printer, PrinterService $printers): RedirectResponse
    {
        Gate::authorize('settings.manage');
        abort_unless($printer->branch_id === (int) app(BranchContext::class)->id(), 404);

        $printers->save($printer->branch, [...$this->validated($request, $printer->branch_id, $printer), 'is_active' => $request->boolean('is_active')], $printer);

        return back()->with('status', __('Printer saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $branchId, ?Printer $printer = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('printers', 'name')->where('branch_id', $branchId)->ignore($printer?->id)],
            'role' => ['required', Rule::in(Printer::ROLES)],
            'paper_width' => ['required', 'integer', Rule::in(Printer::PAPER_WIDTHS)],
            'connection' => ['required', Rule::in(Printer::CONNECTIONS)],
            'address' => ['nullable', 'string', 'max:120'],
            'opens_drawer' => ['nullable', 'boolean'],
        ]);

        return [...$validated, 'opens_drawer' => $request->boolean('opens_drawer')];
    }
}
