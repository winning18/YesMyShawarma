{{--
    Shared by orders/dashboard.blade.php, pos/index.blade.php, and
    orders/history.blade.php — the dashboard now serves two live purposes
    (Orders / POS) plus a read-only history view, and all three carry this
    same title + channel-switcher + shift widget line. $title and $active
    ('orders' or 'pos') are passed in by the including view.

    $isStaff/$forceShiftStart come from the controller — staff specifically
    gets a blocking "start your shift" popup (with an optional
    starting-cash field) before the dashboard is usable, and total_sales is
    required when they end it. Manager keeps the plain click-to-toggle
    behaviour, unchanged. $ordersUrl is the "Orders" button's destination —
    staff and manager land on different routes now that owner/manager's
    route('dashboard') redirects to the business overview instead of this
    board (see OrderDashboardController).

    $hideOperationalControls (owner viewing Order History only — see
    OrderHistoryController) drops the Orders/POS toggle, the shift
    widget, and the order alert below entirely, leaving just the title.
    Owner never operates the live board or POS, so a toggle (or an alert
    prompting them to go accept something) that leads there has no
    business being on a page owner can still reach for read-only review.

    $branchId powers the order alert banner + sound (see
    partials/order-alert-script.blade.php) — this is what tells staff on
    POS (or browsing history) that a new order just landed, without them
    needing to be looking at the Orders board itself. Its "View" link
    reuses $ordersUrl, same destination as the Orders button above —
    staff's own board is already that page, manager's live board is a
    different route, and the banner should never point somewhere
    different from what the rest of this header already considers "the
    board" for whoever's looking at it.
--}}
@unless ($hideOperationalControls ?? false)
    <div x-data="orderAlertWidget({{ $branchId ?? 'null' }})" x-init="init()">
        <div
            x-show="pendingCount > 0"
            x-cloak
            class="mb-3 flex items-center justify-between gap-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-2"
        >
            <span>
                <span class="font-semibold" x-text="pendingCount"></span>
                {{ __('order(s) waiting for acceptance!') }}
            </span>
            <a href="{{ $ordersUrl }}" class="font-semibold hover:underline shrink-0">{{ __('View →') }}</a>
        </div>
    </div>
    @include('partials.order-alert-script')
@endunless

<div
    class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center"
    x-data="shiftWidget({{ $isStaff ? 'true' : 'false' }}, {{ $forceShiftStart ? 'true' : 'false' }})"
    x-init="init()"
>
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $title }}
    </h2>

    @unless ($hideOperationalControls ?? false)
        <div class="flex items-center justify-center gap-3 order-last sm:order-none">
            <a
                href="{{ $ordersUrl }}"
                class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700 {{ $active === 'orders' ? 'ring-2 ring-offset-2 ring-red-600' : '' }}"
            >{{ __('Orders') }}</a>
            <a
                href="{{ route('dashboard.pos.index') }}"
                class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 {{ $active === 'pos' ? 'ring-2 ring-offset-2 ring-green-600' : '' }}"
            >{{ __('POS') }}</a>
        </div>

        <div class="flex items-center justify-end gap-3 text-sm">
            <span x-show="active" class="text-gray-500">
                {{ __('On shift') }} <span x-text="branch"></span>
            </span>
            <button
                type="button"
                x-show="!active"
                @click="openStartModal()"
                class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900"
            >{{ __('Start shift') }}</button>
            <button
                type="button"
                x-show="active"
                @click="openEndModal()"
                class="px-3 py-1.5 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300"
            >{{ __('End shift') }}</button>
        </div>

        @include('partials.shift-modals')
    @endunless
</div>
