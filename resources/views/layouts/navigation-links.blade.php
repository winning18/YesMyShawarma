{{--
    Shared between the fixed desktop sidebar and the mobile slide-out
    drawer in navigation.blade.php — one list, included twice, so a new
    nav item only ever needs adding in one place.
--}}
{{--
    Dashboard means something different per role (OrderDashboardController):
    staff's is the live acknowledgement/in-progress board — its own header
    (dashboard._channel-header) carries the red Orders / green POS toggle.
    Owner/manager land on the business overview (PerformanceController)
    instead — POS, Orders and shifts are not what either of them operates;
    manager reaches the actual board via the Orders link below, owner has
    no path to it in this nav at all.

    Greyed out (not a link at all) for staff with no active shift — they
    can't access the dashboard until they start one (the sidebar's own
    shift widget, always rendered above/below this list, is how) or log in
    again next working day.
--}}
@if (($isStaff ?? false) && ! ($hasActiveShift ?? false))
    <span
        class="flex items-center px-3 py-2 rounded-md rounded-l-none border-l-4 border-transparent text-sm font-medium text-gray-300 cursor-not-allowed select-none"
        title="{{ __('Start a shift to access the dashboard.') }}"
    >{{ __('Dashboard') }}</span>
@else
    <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
        {{ __('Dashboard') }}
    </x-sidebar-link>
@endif

{{--
    Manager only — the live acknowledgement/in-progress board + POS,
    unchanged from what used to live at "Dashboard" for them. Staff never
    needs this (their Dashboard link already goes straight there); owner
    never gets it at all (PosController/OrderDashboardController redirect
    owner away from these routes regardless of how they're reached).
--}}
@if ($isManager ?? false)
    <x-sidebar-link :href="route('dashboard.orders.live')" :active="request()->routeIs('dashboard.orders.live', 'dashboard.pos.index')">
        {{ __('Orders') }}
    </x-sidebar-link>
@endif

{{--
    Order History > Web / POS — each channel opens its own filtered record
    of every past order, any status, paginated (OrderHistoryController).
    Deliberately separate from Dashboard above: that's for acting on
    orders right now, this is for looking back. Open by default whenever
    the current page is a history page, collapsed otherwise; Alpine state
    resets on every full page load (this is a server-rendered app, not an
    SPA), so this re-evaluates fresh each time rather than trying to
    persist.
--}}
<div x-data="{ historyOpen: {{ request()->routeIs('dashboard.orders.history') ? 'true' : 'false' }} }">
    <button
        type="button"
        @click="historyOpen = ! historyOpen"
        class="w-full flex items-center justify-between px-3 py-2 rounded-md rounded-l-none border-l-4 text-sm font-medium transition duration-150 ease-in-out {{ request()->routeIs('dashboard.orders.history') ? 'border-indigo-400 bg-indigo-50 text-indigo-700 font-semibold' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
    >
        <span>{{ __('Order History') }}</span>
        <svg class="w-4 h-4 shrink-0 transition-transform" :class="{ 'rotate-180': historyOpen }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>

    <div x-show="historyOpen" x-cloak class="mt-1 ml-3 pl-3 border-l border-gray-100 space-y-1">
        <a
            href="{{ route('dashboard.orders.history', ['channel' => 'web']) }}"
            class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.orders.history') && request('channel') === 'web' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
        >{{ __('Web') }}</a>
        <a
            href="{{ route('dashboard.orders.history', ['channel' => 'pos']) }}"
            class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.orders.history') && request('channel') === 'pos' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
        >{{ __('POS') }}</a>
    </div>
</div>

{{--
    Menu Editor > Branch / Menu / Categories / Modifiers / Item availability
    — everything to do with what's sellable lives under one dropdown,
    replacing the separate top-level Menu/Categories/Option groups links.
    Open by default whenever the current page is any menu-editor page,
    collapsed otherwise; Alpine state resets on every full page load (this
    is a server-rendered app, not an SPA), so this re-evaluates fresh each
    time rather than trying to persist.
--}}
@canany(['menu.toggle_availability', 'menu.edit_content'])
    @php
        $onMenuEditorSection = request()->routeIs('dashboard.menu-items.*', 'dashboard.categories.*', 'dashboard.option-groups.*', 'branches.select');
    @endphp
    <div x-data="{ menuEditorOpen: {{ $onMenuEditorSection ? 'true' : 'false' }} }">
        <button
            type="button"
            @click="menuEditorOpen = ! menuEditorOpen"
            class="w-full flex items-center justify-between px-3 py-2 rounded-md rounded-l-none border-l-4 text-sm font-medium transition duration-150 ease-in-out {{ $onMenuEditorSection ? 'border-indigo-400 bg-indigo-50 text-indigo-700 font-semibold' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
        >
            <span>{{ __('Menu Editor') }}</span>
            <svg class="w-4 h-4 shrink-0 transition-transform" :class="{ 'rotate-180': menuEditorOpen }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <div x-show="menuEditorOpen" x-cloak class="mt-1 ml-3 pl-3 border-l border-gray-100 space-y-1">
            <a
                href="{{ route('branches.select', ['then' => 'menu']) }}"
                class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('branches.select') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
            >{{ __('Branch') }}</a>

            @can('menu.edit_content')
                <a
                    href="{{ route('dashboard.menu-items.index') }}"
                    class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.menu-items.index') || request()->routeIs('dashboard.menu-items.create') || request()->routeIs('dashboard.menu-items.edit') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                >{{ __('Menu') }}</a>
            @endcan

            @can('menu.edit_content')
                <a
                    href="{{ route('dashboard.categories.index') }}"
                    class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.categories.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                >{{ __('Categories') }}</a>
                <a
                    href="{{ route('dashboard.option-groups.index') }}"
                    class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.option-groups.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                >{{ __('Modifiers') }}</a>
            @endcan

            @can('menu.toggle_availability')
                <a
                    href="{{ route('dashboard.menu-items.availability') }}"
                    class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.menu-items.availability') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                >{{ __('Item availability') }}</a>
            @endcan

            @can('menu.edit_content')
                <a
                    href="{{ route('dashboard.menu-items.timetable') }}"
                    class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.menu-items.timetable') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                >{{ __('Time table') }}</a>
            @endcan
        </div>
    </div>
@endcanany

@can('menu.edit_content')
    <x-sidebar-link :href="route('dashboard.hero-slider.index')" :active="request()->routeIs('dashboard.hero-slider.*')">
        {{ __('Hero Slider') }}
    </x-sidebar-link>
@endcan

@can('branches.manage')
    <x-sidebar-link :href="route('dashboard.branches.index')" :active="request()->routeIs('dashboard.branches.*')">
        {{ __('Branches') }}
    </x-sidebar-link>
@endcan

@can('promotions.manage')
    <x-sidebar-link :href="route('dashboard.promotions.index')" :active="request()->routeIs('dashboard.promotions.*')">
        {{ __('Promotions') }}
    </x-sidebar-link>
@endcan

@can('customers.view')
    <x-sidebar-link :href="route('dashboard.customers.index')" :active="request()->routeIs('dashboard.customers.*')">
        {{ __('Customers') }}
    </x-sidebar-link>
@endcan

@can('reviews.moderate')
    <x-sidebar-link :href="route('dashboard.reviews.index')" :active="request()->routeIs('dashboard.reviews.*')">
        {{ __('Reviews') }}
    </x-sidebar-link>
@endcan

@can('reports.view_operational')
    <x-sidebar-link :href="route('dashboard.reports.index')" :active="request()->routeIs('dashboard.reports.*')">
        {{ __('Reports and invoices') }}
    </x-sidebar-link>
@endcan

{{--
    orders.refund (owner/manager/general_manager — full rights) or
    orders.refund_request (staff — request + complete an approved one,
    view-only otherwise) — everyone who can touch a refund at all gets
    this tab. RefundController::index() scopes what each of them actually
    sees; the view itself hides Approve/Deny from anyone without
    orders.refund.
--}}
@canany(['orders.refund', 'orders.refund_request'])
    <x-sidebar-link :href="route('dashboard.refunds.index')" :active="request()->routeIs('dashboard.refunds.*')">
        {{ __('Refunds') }}
    </x-sidebar-link>
@endcanany

{{--
    Weekly opening schedule, owner+manager only — reuses
    reports.view_financial (already exactly that audience) rather than a
    dedicated permission. Its own sidebar item, not a Reports and invoices
    tab, so it needed its own route namespace (dashboard.working-hours.*)
    rather than dashboard.reports.* — sharing that prefix would have made
    the Reports and invoices link above light up as active on this page
    too, since its :active check is a wildcard.
--}}
@can('reports.view_financial')
    <x-sidebar-link :href="route('dashboard.working-hours.index')" :active="request()->routeIs('dashboard.working-hours.*')">
        {{ __('Working Hours') }}
    </x-sidebar-link>
@endcan

{{--
    stock.manage (owner + stock_manager) reaches the full admin screen
    (add/edit items, restock, history); stock.record_sale-only holders
    (staff/manager/general_manager) land on the sales-recording screen
    instead — RecordSale is the narrower ability, so it's checked second.
--}}
@can('stock.manage')
    <x-sidebar-link :href="route('dashboard.stock.index')" :active="request()->routeIs('dashboard.stock.*')">
        {{ __('Stock') }}
    </x-sidebar-link>
@elsecan('stock.record_sale')
    <x-sidebar-link :href="route('dashboard.stock.sales')" :active="request()->routeIs('dashboard.stock.*')">
        {{ __('Stock') }}
    </x-sidebar-link>
@endcan

{{--
    users.create_operational (general_manager) reaches the same index/create
    screens as users.manage (owner) — see UserManagementController's
    authorizeView()/authorizeCreate() — so it needs the same nav entry, or
    the "add a staff/rider" capability would have no way to be discovered.
    A plain manager (users.transfer_branch only, no create ability) still
    has no link here — same as before this role existed.
--}}
@canany(['users.manage', 'users.create_operational'])
    <x-sidebar-link :href="route('dashboard.users.index')" :active="request()->routeIs('dashboard.users.*')">
        {{ __('Users') }}
    </x-sidebar-link>
@endcanany

{{--
    Settings > General / Staff members — same collapsible-group pattern as
    Menu Editor above. Staff members (the public "Meet our staff" roster on
    the About page) lives here rather than its own top-level sidebar item
    since it's business-wide configuration content, not an operational
    resource — same audience (settings.manage: owner/manager/
    general_manager) as the existing order-reference-prefix settings.
--}}
@can('settings.manage')
    @php
        $onSettingsSection = request()->routeIs('dashboard.settings.*', 'dashboard.staff-members.*');
    @endphp
    <div x-data="{ settingsOpen: {{ $onSettingsSection ? 'true' : 'false' }} }">
        <button
            type="button"
            @click="settingsOpen = ! settingsOpen"
            class="w-full flex items-center justify-between px-3 py-2 rounded-md rounded-l-none border-l-4 text-sm font-medium transition duration-150 ease-in-out {{ $onSettingsSection ? 'border-indigo-400 bg-indigo-50 text-indigo-700 font-semibold' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
        >
            <span>{{ __('Settings') }}</span>
            <svg class="w-4 h-4 shrink-0 transition-transform" :class="{ 'rotate-180': settingsOpen }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <div x-show="settingsOpen" x-cloak class="mt-1 ml-3 pl-3 border-l border-gray-100 space-y-1">
            <a
                href="{{ route('dashboard.settings.index') }}"
                class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.settings.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
            >{{ __('General') }}</a>
            <a
                href="{{ route('dashboard.staff-members.index') }}"
                class="block px-3 py-1.5 rounded-md text-sm transition duration-150 ease-in-out {{ request()->routeIs('dashboard.staff-members.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
            >{{ __('Staff members') }}</a>
        </div>
    </div>
@endcan
