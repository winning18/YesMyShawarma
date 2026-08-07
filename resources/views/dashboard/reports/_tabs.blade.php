{{--
    Shared by all four Reports and invoices pages — $active is one of
    'detailed' | 'invoices' | 'weekly' | 'today'. Each tab is a full
    navigation (own route, own query params), not a client-side switch —
    same server-rendered tab convention as the Performance page.

    Today sits outside the reports.view_financial guard, same as Detailed
    reports — reaching this partial at all already required
    reports.view_operational (every page including it authorizes that),
    and Today is deliberately staff-visible, unlike Invoices/Weekly.
--}}
<div>
    <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-4">{{ __('Reports and invoices') }}</h2>

    <div class="border-b border-gray-200 flex items-center gap-6">
        <a
            href="{{ route('dashboard.reports.index') }}"
            class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $active === 'detailed' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >{{ __('Detailed reports') }}</a>
        @can('reports.view_financial')
            <a
                href="{{ route('dashboard.reports.invoices.index') }}"
                class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $active === 'invoices' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >{{ __('Invoices and sales') }}</a>
            <a
                href="{{ route('dashboard.reports.weekly.index') }}"
                class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $active === 'weekly' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >{{ __('Weekly report') }}</a>
        @endcan
        <a
            href="{{ route('dashboard.reports.today.index') }}"
            class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $active === 'today' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >{{ __('Today') }}</a>
    </div>
</div>
