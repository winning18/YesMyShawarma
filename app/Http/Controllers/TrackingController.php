<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderTrackingResource;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Customers\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function show(Order $order): View
    {
        return view('tracking.show', ['order' => $order]);
    }

    public function data(Order $order): JsonResource
    {
        return new OrderTrackingResource($order->load(['items.menuItem', 'items.options', 'branch', 'rider', 'customer']));
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
