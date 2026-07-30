<?php

namespace App\Http\Controllers;

use App\Exceptions\ShiftException;
use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Shifts\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function show(Request $request, ShiftService $shifts): JsonResponse
    {
        $shift = $shifts->activeFor($request->user());

        return response()->json([
            'active' => (bool) $shift,
            'started_at' => $shift?->started_at?->toIso8601String(),
            'branch' => $shift?->branch?->name,
        ]);
    }

    public function start(Request $request, ShiftService $shifts, BranchContext $context): JsonResponse
    {
        $branchId = $context->id();

        if (! $branchId) {
            return response()->json(['message' => 'Select a branch before starting a shift.'], 422);
        }

        $validated = $request->validate(['opening_note' => ['nullable', 'string', 'max:255']]);

        try {
            $shifts->start($request->user(), Branch::findOrFail($branchId), $validated['opening_note'] ?? null);
        } catch (ShiftException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Shift started.']);
    }

    public function end(Request $request, ShiftService $shifts): JsonResponse
    {
        $shift = $shifts->activeFor($request->user());

        if (! $shift) {
            return response()->json(['message' => 'No open shift to end.'], 422);
        }

        $validated = $request->validate(['closing_note' => ['nullable', 'string', 'max:255']]);

        $shifts->end($shift, $validated['closing_note'] ?? null);

        return response()->json(['message' => 'Shift ended.']);
    }
}
