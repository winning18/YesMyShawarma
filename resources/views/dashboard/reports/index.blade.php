<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'detailed'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-8">
        <div x-data="{ range: '{{ $range ?? '' }}' }" class="bg-white shadow rounded-lg p-4">
            <form method="GET" action="{{ route('dashboard.reports.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="range">{{ __('When') }}</label>
                    <select id="range" name="range" x-model="range" class="rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('Last 7 days (default)') }}</option>
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
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="from">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <div x-show="range === 'custom'" x-cloak>
                    <label class="block text-xs font-medium text-gray-500 mb-1" for="to">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Apply') }}</button>
            </form>
        </div>

        {{-- Operational --}}
        <section class="space-y-4">
            <h3 class="font-semibold text-gray-800">{{ __('Operational') }}</h3>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Orders') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $operational['total_orders'] }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Avg. time to accept') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $operational['avg_time_to_accept_minutes'] !== null ? $operational['avg_time_to_accept_minutes'].' '.__('min') : 'N/A' }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Avg. prep time') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $operational['avg_prep_time_minutes'] !== null ? $operational['avg_prep_time_minutes'].' '.__('min') : 'N/A' }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Avg. delivery time') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $operational['avg_delivery_time_minutes'] !== null ? $operational['avg_delivery_time_minutes'].' '.__('min') : 'N/A' }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Escalations') }}</p>
                    <p class="text-xl font-semibold {{ $operational['escalations'] > 0 ? 'text-brand-red' : 'text-gray-800' }}">{{ $operational['escalations'] }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr><th class="px-4 py-2">{{ __('Day') }}</th><th class="px-4 py-2 text-right">{{ __('Orders') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($operational['orders_by_day'] as $day => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800">{{ \Illuminate\Support\Carbon::parse($day)->format('d M') }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ $count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr><th class="px-4 py-2">{{ __('Status') }}</th><th class="px-4 py-2 text-right">{{ __('Orders') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($operational['status_breakdown'] as $status => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800 capitalize">{{ str_replace('_', ' ', $status) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ $count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">{{ __('No orders in this range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr><th class="px-4 py-2">{{ __('Channel') }}</th><th class="px-4 py-2 text-right">{{ __('Orders') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($operational['orders_by_channel'] as $channel => $count)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800 uppercase">{{ $channel }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ $count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">{{ __('No orders in this range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Financial --}}
        @if ($canViewFinancial)
            <section class="space-y-4">
                <h3 class="font-semibold text-gray-800">{{ __('Financial') }}</h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white shadow rounded-lg p-4">
                        <p class="text-xs text-gray-500">{{ __('Revenue') }}</p>
                        <p class="text-xl font-semibold text-gray-800">GH₵{{ number_format($financial['revenue_total'] / 100, 2) }}</p>
                    </div>
                    <div class="bg-white shadow rounded-lg p-4">
                        <p class="text-xs text-gray-500">{{ __('Avg. order value') }}</p>
                        <p class="text-xl font-semibold text-gray-800">GH₵{{ number_format($financial['average_order_value'] / 100, 2) }}</p>
                    </div>
                    <div class="bg-white shadow rounded-lg p-4">
                        <p class="text-xs text-gray-500">{{ __('Discounts given') }}</p>
                        <p class="text-xl font-semibold text-gray-800">GH₵{{ number_format($financial['discount_total'] / 100, 2) }}</p>
                    </div>
                    <div class="bg-white shadow rounded-lg p-4">
                        <p class="text-xs text-gray-500">{{ __('Refunds') }}</p>
                        <p class="text-xl font-semibold text-gray-800">GH₵{{ number_format($financial['refund_total'] / 100, 2) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr><th class="px-4 py-2">{{ __('Day') }}</th><th class="px-4 py-2 text-right">{{ __('Revenue') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($financial['revenue_by_day'] as $day => $amount)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-800">{{ \Illuminate\Support\Carbon::parse($day)->format('d M') }}</td>
                                        <td class="px-4 py-2 text-right text-gray-500">GH₵{{ number_format($amount / 100, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr><th class="px-4 py-2">{{ __('Payment method') }}</th><th class="px-4 py-2 text-right">{{ __('Revenue') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($financial['revenue_by_payment_method'] as $method => $amount)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-800 capitalize">{{ $method }}</td>
                                        <td class="px-4 py-2 text-right text-gray-500">GH₵{{ number_format($amount / 100, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">{{ __('No revenue in this range.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr><th class="px-4 py-2">{{ __('Channel') }}</th><th class="px-4 py-2 text-right">{{ __('Revenue') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($financial['revenue_by_channel'] as $channel => $amount)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-800 uppercase">{{ $channel }}</td>
                                        <td class="px-4 py-2 text-right text-gray-500">GH₵{{ number_format($amount / 100, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">{{ __('No revenue in this range.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
