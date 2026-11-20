<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Services\Printing\PrintAgentService;
use App\Services\Printing\PrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Express POS Print Agent's side of printing (step 8b): it connects
 * once with a one-time code, then collects jobs every few seconds and
 * reports whether each one printed.
 */
class PrintAgentController extends Controller
{
    public function connect(Request $request, PrintAgentService $agents): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $connected = $agents->connect($validated['code']);

        if (! $connected) {
            return response()->json(['message' => 'That code is wrong or has expired. Get a new one from the Printers page.'], 422);
        }

        return response()->json([
            'token' => $connected['token'],
            'agent' => $connected['agent']->name,
            'branch' => $connected['agent']->branch?->name,
            'business' => config('app.name'),
        ]);
    }

    public function jobs(Request $request, PrintJobService $jobs): JsonResponse
    {
        return response()->json(['jobs' => $jobs->claim($this->agent($request))]);
    }

    public function report(Request $request, PrintJob $job, PrintJobService $jobs): JsonResponse
    {
        $validated = $request->validate([
            'printed' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $jobs->report($this->agent($request), $job, (bool) $validated['printed'], $validated['error'] ?? null);

        return response()->json(['ok' => true]);
    }

    private function agent(Request $request): PrintAgent
    {
        return $request->attributes->get('printAgent');
    }
}
