<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Branches\BranchContext;
use App\Services\Shifts\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderDashboardController extends Controller
{
    private const VISIBLE_STATUSES = ['paid', 'accepted', 'preparing', 'ready', 'dispatched'];

    /**
     * "Dashboard" means something different per role: staff's is (and has
     * always been) this live acknowledgement board — POS/Orders and shifts
     * are the actual job. Owner/manager/general_manager land on the
     * business overview instead (PerformanceController) — POS, Orders and
     * starting a shift are not what an owner does, and a manager or
     * general_manager reaches the exact same board they always had via
     * the dedicated Orders nav item (live() below), not this route.
     */
    public function index(Request $request, BranchContext $context, ShiftService $shifts): View|RedirectResponse
    {
        Gate::authorize('viewAny', Order::class);

        $user = $request->user();
        $branchId = $context->id();

        // hasRoleAtAnyBranch, not primaryRoleFor($user, $branchId) — owner
        // commonly has no resolved branch at all (their default is the
        // cross-branch aggregate view, ResolveCurrentBranch never forces
        // one), so a branch-dependent check would silently miss them and
        // fall through to rendering the live board instead of redirecting.
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $role = $branchId ? $context->primaryRoleFor($user, $branchId) : null;

        if ($isOwner || in_array($role, ['manager', 'general_manager'], true)) {
            return redirect()->route('dashboard.performance');
        }

        return $this->board($user, $branchId, $context, $shifts, route('dashboard'));
    }

    /**
     * dashboard.orders.live — manager's route to the same board staff
     * lands on directly, now that index() redirects manager away from
     * route('dashboard'). Never reached by staff (their Dashboard link
     * already goes straight to index()) or owner (no nav path to this
     * route at all — see navigation-links.blade.php).
     */
    public function live(Request $request, BranchContext $context, ShiftService $shifts): View
    {
        Gate::authorize('viewAny', Order::class);

        return $this->board($request->user(), $context->id(), $context, $shifts, route('dashboard.orders.live'));
    }

    private function board(User $user, ?int $branchId, BranchContext $context, ShiftService $shifts, string $ordersUrl): View
    {
        $isStaff = $branchId && $context->primaryRoleFor($user, $branchId) === 'staff';

        return view('orders.dashboard', [
            'branchId' => $branchId,
            'isStaff' => $isStaff,
            'forceShiftStart' => $isStaff && ! $shifts->activeFor($user),
            'ordersUrl' => $ordersUrl,
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

        $orders = Order::with(['items.options', 'customer', 'rider', 'events'])
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->orderBy('placed_at')
            ->get();

        return OrderResource::collection($orders);
    }
}
