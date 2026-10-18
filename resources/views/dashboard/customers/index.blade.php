<x-app-layout>
    <x-slot name="header">
        @include('dashboard.customers._tabs', ['active' => 'customers'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        <form method="GET" action="{{ route('dashboard.customers.index') }}">
            <input
                type="text" name="search" value="{{ $search }}"
                placeholder="{{ __('Search by name or phone…') }}"
                class="w-full max-w-sm rounded-md border-gray-300 text-sm"
            >
        </form>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Phone') }}</th>
                        <th class="px-4 py-2">{{ __('Location') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Orders') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Lifetime value') }}</th>
                        <th class="px-4 py-2">{{ __('Last order') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="px-4 py-2 text-gray-800">{{ $customer->name ?? __('(no name)') }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $customer->phone }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $customer->location ?? 'N/A' }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $customer->orders_count }}</td>
                            <td class="px-4 py-2 text-right font-medium text-gray-800">GH₵{{ number_format(($customer->lifetime_value ?? 0) / 100, 2) }}</td>
                            <td class="px-4 py-2 text-gray-500">
                                {{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->timezone('Africa/Accra')->format('d M Y') : 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No customers found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $customers->links() }}
    </div>
</x-app-layout>
