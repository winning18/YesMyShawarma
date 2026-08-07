<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\Branches\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderAvailabilityController extends Controller
{
    /**
     * Riders currently on shift at the resolved branch — for the manual
     * assign-rider dropdown (orders.assign_rider). Not every "on shift"
     * rider is necessarily free (they may already be carrying an order);
     * that's shown alongside the name rather than filtered out, since
     * manual assignment is a deliberate override that doesn't have to
     * respect auto-assignment's eligibility rule (see orders.md).
     */
    public function index(Request $request, BranchContext $context): JsonResponse
    {
        abort_unless($request->user()->can('orders.assign_rider'), 403);

        $shifts = Shift::with('user')
            ->where('branch_id', $context->id())
            ->whereNull('ended_at')
            ->get();

        return response()->json([
            'data' => $shifts->map(fn (Shift $shift) => [
                'id' => $shift->user->id,
                'name' => $shift->user->name,
            ]),
        ]);
    }
}
