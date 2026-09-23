<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Orders\RiderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderAvailabilityController extends Controller
{
    /**
     * Riders currently logged in at the resolved branch — for the manual
     * assign-rider dropdown (orders.assign_rider). Not every logged-in
     * rider is necessarily free (they may already be carrying an order);
     * that's shown alongside the name rather than filtered out, since
     * manual assignment is a deliberate override that doesn't have to
     * respect auto-assignment's eligibility rule (see orders.md).
     *
     * Filtered to the 'rider' role specifically, and to
     * RiderAssignmentService::loggedInRiderIds() for what "logged in at
     * this branch" actually means — same rule auto-assignment uses, so
     * the two never disagree about who counts as available.
     */
    public function index(Request $request, BranchContext $context, RiderAssignmentService $riders): JsonResponse
    {
        abort_unless($request->user()->can('orders.assign_rider'), 403);

        $riderIds = $context->usersWithRole('rider', $context->id())->pluck('id');
        $availableIds = $riders->loggedInRiderIds($riderIds, $context->id());

        return response()->json([
            'data' => User::whereIn('id', $availableIds)->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ]),
        ]);
    }
}
