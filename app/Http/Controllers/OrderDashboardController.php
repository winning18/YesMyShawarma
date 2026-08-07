<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Branches\BranchContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderDashboardController extends Controller
{
    private const VISIBLE_STATUSES = ['paid', 'accepted', 'preparing', 'ready', 'dispatched'];

    public function index(BranchContext $context): View
    {
        Gate::authorize('viewAny', Order::class);

        return view('orders.dashboard', [
            'branchId' => $context->id(),
        ]);
    }

    /**
     * JSON refetch endpoint. Broadcasts (OrderPlaced/OrderStatusChanged) are
     * cosmetic per realtime.md — this is what the dashboard actually
     * reconciles against, on every broadcast and on reconnect.
     */
    public function data(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::with(['items.options', 'customer', 'rider'])
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->orderBy('placed_at')
            ->get();

        return OrderResource::collection($orders);
    }
}
