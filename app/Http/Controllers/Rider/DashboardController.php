<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Services\Branches\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * A rider only ever sees orders assigned to them — there's no claimable
     * pool (see orders.md's rider assignment section). rider_id alone is
     * the correct, sufficient filter; BranchScope is deliberately bypassed
     * in data() below, not relied on — a rider can now be assigned to more
     * than one branch (permissions.md) and switch which one is "current"
     * mid-delivery, and an order they're actually carrying must never
     * disappear from their own dashboard just because it was placed at a
     * branch that isn't their ambient session branch right now.
     */
    private const ACTIVE_STATUSES = ['ready', 'dispatched'];

    public function index(BranchContext $context): View
    {
        Gate::authorize('viewAny', Order::class);

        return view('rider.dashboard', [
            'branchId' => $context->id(),
        ]);
    }

    public function data(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::withoutGlobalScope(BranchScope::class)
            ->with(['items.options', 'customer', 'branch', 'payments'])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('rider_id', $request->user()->id)
            ->orderBy('placed_at')
            ->get();

        return OrderResource::collection($orders);
    }
}
