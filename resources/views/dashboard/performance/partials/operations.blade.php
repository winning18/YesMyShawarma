@if ($branchFilterId !== null)
    <div class="flex items-center gap-2 text-sm text-gray-600">
        <span>{{ __('Showing') }}: <span class="font-semibold text-gray-800">{{ $branchOptions->firstWhere('id', $branchFilterId)?->name }}</span></span>
        <a href="{{ route('dashboard.performance', ['tab' => 'operations', 'range' => $rangeKey]) }}" class="text-green-700 hover:underline">{{ __('Clear (view all branches)') }}</a>
    </div>
@endif

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

{{-- Owner only — a single-branch manager comparing branches would mean
     leaking other branches' revenue (PerformanceController leaves
     $branches null for anyone who isn't actually 'owner'). --}}
@if ($branches !== null)
    <div class="space-y-4">
        <h3 class="font-semibold text-gray-800">{{ __('By branch') }}</h3>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Branch') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Orders') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Revenue') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Avg. time to accept') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Avg. prep time') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Avg. delivery time') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Escalated') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($branches as $row)
                        <tr>
                            <td class="px-4 py-2 text-gray-800">
                                <a href="{{ route('dashboard.performance', ['tab' => 'operations', 'range' => $rangeKey, 'branch' => $row['branch']->id]) }}" class="hover:underline hover:text-green-700">{{ $row['branch']->name }}</a>
                            </td>
                            <td class="px-4 py-2 text-right text-gray-700">{{ $row['orders'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-700">GH₵{{ number_format($row['revenue'] / 100, 2) }}</td>
                            <td class="px-4 py-2 text-right text-gray-700">{{ $row['avg_time_to_accept_minutes'] !== null ? $row['avg_time_to_accept_minutes'].' '.__('min') : 'N/A' }}</td>
                            <td class="px-4 py-2 text-right text-gray-700">{{ $row['avg_prep_time_minutes'] !== null ? $row['avg_prep_time_minutes'].' '.__('min') : 'N/A' }}</td>
                            <td class="px-4 py-2 text-right text-gray-700">{{ $row['avg_delivery_time_minutes'] !== null ? $row['avg_delivery_time_minutes'].' '.__('min') : 'N/A' }}</td>
                            <td class="px-4 py-2 text-right {{ $row['escalated'] > 0 ? 'text-brand-red font-semibold' : 'text-gray-500' }}">{{ $row['escalated'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No branches yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
