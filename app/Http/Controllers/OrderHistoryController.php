<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use App\Models\Order;
use App\Services\Branches\BranchContext;
use App\Services\Orders\RefundService;
use App\Services\Shifts\ShiftService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The sidebar's Orders > Web/POS dropdown links here — a paginated record
 * of every order (any status), as distinct from OrderDashboardController's
 * live acknowledgement/in-progress board. Same ?channel= filter and the
 * same orders.view permission, different purpose: history, not action.
 */
class OrderHistoryController extends Controller
{
    /**
     * @var list<string>
     */
    private const RANGE_PRESETS = ['today', '7', '30', 'week', 'month', 'last_month', 'custom'];

    public function index(Request $request, BranchContext $context, ShiftService $shifts): View
    {
        Gate::authorize('viewAny', Order::class);

        $channel = $this->channelFilter($request);
        $filters = $this->filters($request);
        [$from, $to] = $this->dateRange($filters);

        $user = $request->user();
        $branchId = $context->id();
        $isOwner = $context->hasRoleAtAnyBranch($user, 'owner');
        $isStaff = $branchId && $context->primaryRoleFor($user, $branchId) === 'staff';

        return view('orders.history', [
            'orders' => $this->orders($channel, $filters, $from, $to),
            'channel' => $channel,
            'filters' => $filters,
            'statuses' => Order::STATUSES,
            'locations' => DeliveryArea::orderBy('name')->pluck('name'),
            'isStaff' => $isStaff,
            'forceShiftStart' => $isStaff && ! $shifts->activeFor($user),
            'ordersUrl' => $isStaff ? route('dashboard') : route('dashboard.orders.live'),
            'branchId' => $branchId,
            // Owner can still review history, but the live board/POS it
            // toggles to and the shift widget are not owner features at
            // all — see dashboard/_channel-header.blade.php.
            'hideOperationalControls' => $isOwner,
        ]);
    }

    public function show(Order $order, RefundService $refunds): View
    {
        Gate::authorize('view', $order);

        return view('orders.show', [
            'order' => $order->load([
                'items.options', 'customer', 'branch', 'rider', 'promotion',
                'payments',
                'events' => fn ($query) => $query->orderBy('created_at'),
                'refunds' => fn ($query) => $query->latest(),
                'refunds.requestedBy', 'refunds.completedBy',
            ]),
            // orders.refund (owner/manager/general_manager, payments.md's
            // Refunds section — all three have identical rights) skips
            // the approval step entirely, unlike a plain staff request.
            'canRefundDirectly' => Gate::allows('orders.refund'),
            'remainingRefundBalance' => $order->payment_status === 'paid' ? $refunds->remainingBalance($order) : 0,
        ]);
    }

    /**
     * @return array{search: ?string, status: ?string, fulfilment_type: ?string, location: ?string, range: ?string, from: ?string, to: ?string}
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Order::STATUSES)],
            'fulfilment_type' => ['nullable', 'in:pickup,delivery'],
            'location' => ['nullable', 'string', 'max:255'],
            'range' => ['nullable', Rule::in(self::RANGE_PRESETS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    /**
     * Days/weeks/months quick presets, all in Africa/Accra local time since
     * that's the timezone staff actually think in — placed_at is compared
     * against the UTC-converted boundary at query time. 'custom' defers to
     * whatever the from/to inputs carry; any other value (or none) means
     * no date filtering at all.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dateRange(array $filters): array
    {
        $now = Carbon::now('Africa/Accra');

        return match ($filters['range'] ?? null) {
            'today' => [$now->clone()->startOfDay(), $now->clone()->endOfDay()],
            '7' => [$now->clone()->subDays(6)->startOfDay(), $now->clone()->endOfDay()],
            '30' => [$now->clone()->subDays(29)->startOfDay(), $now->clone()->endOfDay()],
            'week' => [$now->clone()->startOfWeek(), $now->clone()->endOfWeek()],
            'month' => [$now->clone()->startOfMonth(), $now->clone()->endOfMonth()],
            'last_month' => [$now->clone()->subMonthNoOverflow()->startOfMonth(), $now->clone()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                filled($filters['from'] ?? null) ? Carbon::parse($filters['from'], 'Africa/Accra') : null,
                filled($filters['to'] ?? null) ? Carbon::parse($filters['to'], 'Africa/Accra') : null,
            ],
            default => [null, null],
        };
    }

    private function orders(?string $channel, array $filters, ?Carbon $from, ?Carbon $to): LengthAwarePaginator
    {
        return Order::with('customer')
            ->when($channel, fn ($query, $value) => $query->where('channel', $value))
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(
                fn ($query) => $query->where('reference', 'like', "%{$value}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%"))
            ))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['fulfilment_type'] ?? null, fn ($query, $value) => $query->where('fulfilment_type', $value))
            ->when($filters['location'] ?? null, fn ($query, $value) => $query->where('delivery_address_snapshot->area_name', $value))
            ->when($from, fn ($query) => $query->where('placed_at', '>=', $from->clone()->utc()))
            ->when($to, fn ($query) => $query->where('placed_at', '<=', $to->clone()->utc()))
            ->orderByDesc('placed_at')
            ->paginate(20)
            ->withQueryString();
    }

    private function channelFilter(Request $request): ?string
    {
        $channel = $request->query('channel');

        return in_array($channel, ['web', 'pos'], true) ? $channel : null;
    }
}
