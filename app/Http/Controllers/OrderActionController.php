<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Orders\OrderStateMachine;
use App\Services\Orders\RiderAssignmentService;
use App\Services\Shifts\ShiftService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderActionController extends Controller
{
    public function accept(Order $order, OrderStateMachine $stateMachine, BranchContext $context, ShiftService $shifts, Request $request): OrderResource
    {
        Gate::authorize('accept', $order);

        $stateMachine->transition(
            $order, 'accepted', $context->primaryRoleFor($request->user(), $order->branch_id), $request->user()->id,
            shiftId: $shifts->activeFor($request->user())?->id,
        );

        return new OrderResource($order->fresh(['items.options', 'customer']));
    }

    public function reject(Order $order, OrderStateMachine $stateMachine, BranchContext $context, ShiftService $shifts, Request $request): OrderResource
    {
        Gate::authorize('reject', $order);

        $stateMachine->transition(
            $order, 'rejected', $context->primaryRoleFor($request->user(), $order->branch_id), $request->user()->id,
            shiftId: $shifts->activeFor($request->user())?->id,
        );

        return new OrderResource($order->fresh(['items.options', 'customer']));
    }

    public function advance(Request $request, Order $order, OrderStateMachine $stateMachine, BranchContext $context, ShiftService $shifts): OrderResource
    {
        Gate::authorize('advanceStatus', $order);

        $validated = $request->validate([
            'to' => ['required', 'string', Rule::in(['preparing', 'ready', 'dispatched', 'delivered'])],
        ]);

        $stateMachine->transition(
            $order, $validated['to'], $context->primaryRoleFor($request->user(), $order->branch_id), $request->user()->id,
            shiftId: $shifts->activeFor($request->user())?->id,
        );

        return new OrderResource($order->fresh(['items.options', 'customer']));
    }

    public function cancel(Request $request, Order $order, OrderStateMachine $stateMachine, BranchContext $context, ShiftService $shifts): OrderResource
    {
        Gate::authorize('cancel', $order);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $stateMachine->transition(
            $order, 'cancelled', $context->primaryRoleFor($request->user(), $order->branch_id), $request->user()->id,
            cancellationReason: $validated['reason'],
            shiftId: $shifts->activeFor($request->user())?->id,
        );

        return new OrderResource($order->fresh(['items.options', 'customer']));
    }

    /**
     * Manual override — the fallback path, not the normal one. Most
     * assignment happens automatically when an order reaches "ready" (see
     * OrderStateMachine + RiderAssignmentService::autoAssign). This exists
     * for when nobody was eligible, or a correction is needed.
     */
    public function assignRider(Request $request, Order $order, RiderAssignmentService $riderAssignment, BranchContext $context, ShiftService $shifts): OrderResource
    {
        Gate::authorize('assignRider', $order);

        $validated = $request->validate([
            'rider_id' => [
                'required', 'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($order, $context): void {
                    $holdsRiderRole = $context->usersWithRole('rider', $order->branch_id)->contains('id', (int) $value);
                    $onShift = Shift::where('user_id', $value)
                        ->where('branch_id', $order->branch_id)
                        ->whereNull('ended_at')
                        ->exists();

                    if (! $holdsRiderRole || ! $onShift) {
                        $fail('Please select a rider on shift at this branch.');
                    }
                },
            ],
        ]);

        $rider = User::findOrFail($validated['rider_id']);

        $riderAssignment->assign(
            $order, $rider, $request->user(),
            $context->primaryRoleFor($request->user(), $order->branch_id),
            $shifts->activeFor($request->user())?->id,
        );

        return new OrderResource($order->fresh(['items.options', 'customer']));
    }
}
