{{--
    Shared by the Customers pages — $active is 'customers' | 'broadcasts'.
    Server-rendered tabs, same convention as dashboard/reports/_tabs.
--}}
<div>
    <div class="flex justify-between items-center mb-4">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Customers') }}</h2>
        @if ($active === 'customers')
            <a href="{{ route('dashboard.customers.export', request()->query()) }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                {{ __('Export CSV') }}
            </a>
        @endif
    </div>

    <div class="border-b border-gray-200 flex items-center gap-6">
        <a
            href="{{ route('dashboard.customers.index') }}"
            class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $active === 'customers' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
        >{{ __('Customer list') }}</a>
        @can('customers.broadcast')
            <a
                href="{{ route('dashboard.customers.broadcasts.index') }}"
                class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $active === 'broadcasts' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >{{ __('SMS broadcasts') }}</a>
        @endcan
    </div>
</div>
