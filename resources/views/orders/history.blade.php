<x-app-layout>
    <x-slot name="header">
        @include('dashboard._channel-header', [
            'title' => match ($channel) {
                'web' => __('Web order history'),
                'pos' => __('POS order history'),
                default => __('Order history'),
            },
            'active' => 'orders',
        ])
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php
                $hasActiveFilters = collect($filters)->filter()->isNotEmpty();
            @endphp

            <div
                x-data="{ range: '{{ $filters['range'] ?? '' }}' }"
                class="bg-white shadow rounded-lg p-4"
            >
                <form method="GET" action="{{ route('dashboard.orders.history') }}" class="space-y-3">
                    @if ($channel)
                        <input type="hidden" name="channel" value="{{ $channel }}">
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label for="search" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Search') }}</label>
                            <input
                                type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                                placeholder="{{ __('Name, phone, or reference…') }}"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                        </div>

                        <div>
                            <label for="status" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                            <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('Any status') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>
                                        {{ ucwords(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="fulfilment_type" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Fulfilment') }}</label>
                            <select id="fulfilment_type" name="fulfilment_type" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('Any fulfilment') }}</option>
                                <option value="pickup" @selected(($filters['fulfilment_type'] ?? null) === 'pickup')>{{ __('Pickup') }}</option>
                                <option value="delivery" @selected(($filters['fulfilment_type'] ?? null) === 'delivery')>{{ __('Delivery') }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="location" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Location') }}</label>
                            <select id="location" name="location" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('Any location') }}</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location }}" @selected(($filters['location'] ?? null) === $location)>{{ $location }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                        <div>
                            <label for="range" class="block text-xs font-medium text-gray-500 mb-1">{{ __('When') }}</label>
                            <select id="range" name="range" x-model="range" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('Any time') }}</option>
                                <option value="today">{{ __('Today') }}</option>
                                <option value="7">{{ __('Last 7 days') }}</option>
                                <option value="30">{{ __('Last 30 days') }}</option>
                                <option value="week">{{ __('This week') }}</option>
                                <option value="month">{{ __('This month') }}</option>
                                <option value="last_month">{{ __('Last month') }}</option>
                                <option value="custom">{{ __('Custom range…') }}</option>
                            </select>
                        </div>

                        <div x-show="range === 'custom'" x-cloak>
                            <label for="from" class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
                            <input
                                type="datetime-local" id="from" name="from" value="{{ $filters['from'] ?? '' }}"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                        </div>

                        <div x-show="range === 'custom'" x-cloak>
                            <label for="to" class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
                            <input
                                type="datetime-local" id="to" name="to" value="{{ $filters['to'] ?? '' }}"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                                {{ __('Apply filters') }}
                            </button>
                            @if ($hasActiveFilters)
                                <a
                                    href="{{ route('dashboard.orders.history', $channel ? ['channel' => $channel] : []) }}"
                                    class="text-sm text-gray-500 hover:underline"
                                >{{ __('Clear filters') }}</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Reference') }}</th>
                            <th class="px-4 py-2">{{ __('Customer') }}</th>
                            <th class="px-4 py-2">{{ __('Location') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th>
                            <th class="px-4 py-2">{{ __('Fulfilment') }}</th>
                            @unless ($channel)
                                <th class="px-4 py-2">{{ __('Channel') }}</th>
                            @endunless
                            <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                            <th class="px-4 py-2">{{ __('Placed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orders as $order)
                            @php
                                $badgeClass = match (true) {
                                    $order->status === 'delivered' => 'bg-green-50 text-green-700',
                                    in_array($order->status, ['cancelled', 'rejected', 'failed', 'abandoned', 'refunded'], true) => 'bg-red-50 text-red-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr
                                onclick="window.location='{{ route('dashboard.orders.show', $order) }}'"
                                class="cursor-pointer hover:bg-gray-50"
                            >
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('dashboard.orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->reference }}</a>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $order->customer?->name ?? $order->customer?->phone ?? __('—') }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $order->delivery_address_snapshot['area_name'] ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-xs font-medium px-2 py-1 rounded-md capitalize {{ $badgeClass }}">{{ str_replace('_', ' ', $order->status) }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-500 capitalize">{{ $order->fulfilment_type }}</td>
                                @unless ($channel)
                                    <td class="px-4 py-2 text-gray-500 uppercase">{{ $order->channel }}</td>
                                @endunless
                                <td class="px-4 py-2 text-right text-gray-800">GH₵{{ number_format($order->total / 100, 2) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $order->placed_at?->timezone('Africa/Accra')->format('d M Y, H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $channel ? 7 : 8 }}" class="px-4 py-6 text-center text-gray-500">{{ __('No orders match these filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links() }}
        </div>
    </div>

    @include('partials.shift-widget-script')
</x-app-layout>
