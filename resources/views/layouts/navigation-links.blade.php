{{--
    Shared between the fixed desktop sidebar and the mobile slide-out
    drawer in navigation.blade.php — one list, included twice, so a new
    nav item only ever needs adding in one place.
--}}
{{--
    Dashboard — the live acknowledgement/in-progress board. Its own header
    (dashboard._channel-header) carries the red Orders / green POS toggle,
    so this single link is where incoming orders get acknowledged and
    where POS is reached from.
--}}
<x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
    {{ __('Dashboard') }}
</x-sidebar-link>

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

@can('dashboard.performance')
    <x-sidebar-link :href="route('dashboard.performance')" :active="request()->routeIs('dashboard.performance')">
        {{ __('Performance') }}
    </x-sidebar-link>
@endcan

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

@can('reports.view_operational')
    <x-sidebar-link :href="route('dashboard.reports.index')" :active="request()->routeIs('dashboard.reports.*')">
        {{ __('Reports and invoices') }}
    </x-sidebar-link>
@endcan

@can('users.manage')
    <x-sidebar-link :href="route('dashboard.users.index')" :active="request()->routeIs('dashboard.users.*')">
        {{ __('Users') }}
    </x-sidebar-link>
@endcan
