<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Branches\BranchContext;
use App\Services\Orders\OrderStateMachine;
use App\Services\Shifts\ShiftService;
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
}
