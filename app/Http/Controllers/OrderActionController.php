<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Exceptions\ShiftException;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Orders\OrderStateMachine;
use App\Services\Orders\RiderAssignmentService;
use App\Services\Payments\PaymentConfirmationService;
use App\Services\Shifts\ShiftService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderActionController extends Controller
{
    /**
     * Staff specifically must have an active shift at the order's branch
     * to accept it — schema.md already treats shifts as the staff-specific
     * accounting mechanism (total_sales required only for staff, optional
     * for everyone else), so the same line is drawn here: manager/owner/
     * general_manager can still accept without one, same as always. This
     * is what actually makes "an order placed while closed just sits
     * there" true in practice — there's no separate hold state, staff
     * simply can't act on it until they clock in.
     */
    public function accept(Order $order, OrderStateMachine $stateMachine, BranchContext $context, ShiftService $shifts, Request $request): OrderResource|JsonResponse
    {
        Gate::authorize('accept', $order);

        $role = $context->primaryRoleFor($request->user(), $order->branch_id);
        $shift = $shifts->activeFor($request->user());

        if ($role === 'staff' && (! $shift || $shift->branch_id !== $order->branch_id)) {
            return response()->json(['message' => ShiftException::mustBeOnShiftToAccept()->getMessage()], 422);
        }

        $stateMachine->transition(
            $order, 'accepted', $role, $request->user()->id,
            shiftId: $shift?->id,
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
            'to' => ['required', 'string', Rule::in(['preparing', 'ready', 'dispatched', 'delivered', 'failed'])],
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
     * Entering a momo transaction ID staff skipped at placement (see
     * PlaceOrderData::$paymentReference) — flips the order's payment_status
     * to 'paid' once entered. Never touches order.status; the kitchen
     * already started on the order regardless of payment reconciliation.
     */
    /**
     * Called two ways: the order-detail page's plain HTML form (staff
     * entering an ID they skipped at POS placement), and JSON callers per
     * CLAUDE.md's API-first rule — the response shape follows the request,
     * same idea as everywhere else in this controller, just with a redirect
     * fallback added since this is the one action with an HTML-form caller.
     */
    public function confirmMomoPayment(Request $request, Order $order, PaymentConfirmationService $payments, BranchContext $context, ShiftService $shifts): OrderResource|JsonResponse|RedirectResponse
    {
        Gate::authorize('confirmMomoPayment', $order);

        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payments->confirmMomo(
                $order, $validated['transaction_id'],
                $context->primaryRoleFor($request->user(), $order->branch_id), $request->user()->id,
                $shifts->activeFor($request->user())?->id,
            );
        } catch (PaymentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['transaction_id' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return new OrderResource($order->fresh(['items.options', 'customer']));
        }

        return back()->with('status', __('Momo payment confirmed.'));
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
