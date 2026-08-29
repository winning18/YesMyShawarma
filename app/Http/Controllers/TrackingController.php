<?php

namespace App\Http\Controllers;

use App\Exceptions\ReviewException;
use App\Http\Resources\OrderTrackingResource;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Branches\WorkingHoursService;
use App\Services\Customers\CustomerService;
use App\Services\Orders\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function show(Order $order, WorkingHoursService $workingHours): View
    {
        // A fixed fact about this specific order's placement — computed
        // once here rather than folded into OrderTrackingResource's live-
        // polled payload, since (unlike status) it never changes after
        // the order is placed.
        $branchWasClosed = ! $workingHours->isOpenAt($order->branch, $order->placed_at);

        return view('tracking.show', [
            'order' => $order,
            'branchWasClosed' => $branchWasClosed,
            'nextOpeningLabel' => $branchWasClosed ? $workingHours->nextOpening($order->branch, $order->placed_at)?->format('l g:ia') : null,
        ]);
    }

    public function data(Order $order): JsonResource
    {
        return new OrderTrackingResource($order->load(['items.menuItem', 'items.options', 'branch', 'rider', 'customer', 'review']));
    }

    /**
     * Public, token-gated (no login) — same trust model as show()/data()
     * above. Fetch-based (see tracking/show.blade.php's orderTracker()),
     * not a full-page POST, so failures come back as JSON rather than a
     * redirect-with-errors.
     */
    public function storeReview(Request $request, Order $order, ReviewService $reviews): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $review = $reviews->submit($order, $validated['rating'], $validated['comment'] ?? null);
        } catch (ReviewException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'review' => [
                'rating' => $review->rating,
                'comment' => $review->comment,
            ],
        ]);
    }

    /**
     * Nav-level "Track order" entry point — a customer with a direct link
     * (from an order confirmation) never needs this; it's for finding an
     * order without one. Logged-in customers see their history directly;
     * guests look a specific order up by phone + reference, since there's
     * no OTP verification yet to safely list *all* of a phone's orders to
     * an unauthenticated visitor.
     */
    public function lookup(): View
    {
        $customer = Auth::guard('customer')->user();

        return view('tracking.lookup', [
            'orders' => $customer
                ? $customer->orders()->latest('placed_at')->get()
                : null,
        ]);
    }

    public function find(Request $request, CustomerService $customers): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'reference' => ['required', 'string'],
        ]);

        $phone = $customers->normalizeGhanaPhone($validated['phone']);

        $customer = Customer::where('phone', $phone)->first();

        $order = $customer
            ? Order::where('customer_id', $customer->id)->where('reference', $validated['reference'])->first()
            : null;

        if (! $order) {
            return back()->withErrors(['reference' => 'No matching order found for that phone number and reference.']);
        }

        return redirect()->route('tracking.show', $order);
    }
}
