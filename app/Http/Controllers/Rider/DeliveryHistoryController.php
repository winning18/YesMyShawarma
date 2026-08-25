<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryHistoryController extends Controller
{
    /**
     * Past deliveries only — "delivered" or "failed" (see orders.md; no
     * dedicated failed_at column exists, so this orders by updated_at
     * rather than a per-status timestamp). Active orders live on the main
     * rider dashboard, not here.
     */
    public function index(Request $request): View
    {
        return view('rider.history', [
            'orders' => $this->history($request),
        ]);
    }

    private function history(Request $request): LengthAwarePaginator
    {
        return Order::with(['items'])
            ->where('rider_id', $request->user()->id)
            ->whereIn('status', ['delivered', 'failed'])
            ->orderByDesc('updated_at')
            ->paginate(15);
    }
}
