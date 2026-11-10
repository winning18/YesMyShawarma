<?php

namespace App\Http\Controllers\Rider;

use App\Exceptions\RefundException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BranchScope;
use App\Services\Orders\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A rider's side of a refund (rider app → Past deliveries): ask to refund
 * one of their own recent deliveries, and — for a cash order, once an
 * owner, GM or manager has approved it — confirm they've handed the money
 * back. Approval itself stays on the dashboard's Refunds page.
 *
 * Orders and refunds are looked up without BranchScope: a rider's
 * deliveries can be at any branch they ride for, whichever one their
 * session is on right now. The policies check the record's own branch.
 */
class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function store(Request $request, int $order): RedirectResponse
    {
        $order = Order::withoutGlobalScope(BranchScope::class)->findOrFail($order);
        Gate::authorize('requestRiderRefund', $order);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->refunds->requestForRider($order, $request->user(), $validated['reason']);
        } catch (RefundException $e) {
            return back()->withErrors(['refund_'.$order->id => $e->getMessage()]);
        }

        return back()->with('status', __('Refund requested for :reference. A manager will review it.', ['reference' => $order->reference]));
    }

    public function complete(Request $request, int $refund): RedirectResponse
    {
        $refund = Refund::withoutGlobalScope(BranchScope::class)->findOrFail($refund);
        Gate::authorize('completeAsRider', $refund);

        try {
            $this->refunds->complete($refund, $request->user(), 'rider');
        } catch (RefundException $e) {
            return back()->withErrors(['refund_'.$refund->order_id => $e->getMessage()]);
        }

        return back()->with('status', __('Refund marked as given.'));
    }
}
