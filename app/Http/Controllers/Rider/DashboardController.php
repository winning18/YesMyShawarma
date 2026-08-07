<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Branches\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * A rider only ever sees orders assigned to them — there's no claimable
     * pool (see orders.md's rider assignment section). BranchScope already
     * limits this to the branch resolved by the `branch` middleware.
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

        $orders = Order::with(['items.options', 'customer'])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('rider_id', $request->user()->id)
            ->orderBy('placed_at')
            ->get();

        return OrderResource::collection($orders);
    }
}
