@php
    $noPreviousData = ! collect($summary['chart']['previous'])->contains(fn ($v) => $v > 0);
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <x-performance.kpi-card
        :label="__('Sales')"
        :value="'GHS '.number_format($summary['sales']['value'] / 100, 2)"
        :change-pct="$summary['sales']['change_pct']"
        :highlight="true"
    />
    <x-performance.kpi-card
        :label="__('Orders received')"
        :value="number_format($summary['orders']['value'])"
        :change-pct="$summary['orders']['change_pct']"
    />
    <x-performance.kpi-card
        :label="__('Average order value')"
        :value="'GHS '.number_format($summary['average_order_value']['value'] / 100, 2)"
        :change-pct="$summary['average_order_value']['change_pct']"
    />
    <x-performance.kpi-card
        :label="__('Customer conversion')"
        :value="$summary['conversion']['value'] !== null ? number_format($summary['conversion']['value'], 1).'%' : '—'"
        :change-pct="$summary['conversion']['change_pct']"
    />
</div>

@include('dashboard.performance.partials.chart', ['labels' => $summary['chart']['labels'], 'current' => $summary['chart']['current'], 'previous' => $summary['chart']['previous']])

@if ($noPreviousData)
    <p class="text-xs text-gray-400">{{ __('The dotted line (previous period) has no data yet — comparisons will fill in as more history builds up.') }}</p>
@endif

<div class="space-y-4">
    <h3 class="font-semibold text-gray-800">{{ __('Item sales') }}</h3>

    <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">{{ __('Item name') }}</th>
                    <th class="px-4 py-2 text-right">
                        <a href="{{ route('dashboard.performance', array_merge(request()->query(), ['sort' => 'amount_sold', 'dir' => $sort === 'amount_sold' && $dir === 'desc' ? 'asc' : 'desc'])) }}" class="inline-flex items-center gap-1 hover:text-gray-700 {{ $sort === 'amount_sold' ? 'text-green-700 font-semibold' : '' }}">
                            {{ __('Amount sold') }} {{ $sort === 'amount_sold' ? ($dir === 'desc' ? '↓' : '↑') : '' }}
                        </a>
                    </th>
                    <th class="px-4 py-2 text-right">
                        <a href="{{ route('dashboard.performance', array_merge(request()->query(), ['sort' => 'item_sales', 'dir' => $sort === 'item_sales' && $dir === 'desc' ? 'asc' : 'desc'])) }}" class="inline-flex items-center gap-1 hover:text-gray-700 {{ $sort === 'item_sales' ? 'text-green-700 font-semibold' : '' }}">
                            {{ __('Item sales') }} {{ $sort === 'item_sales' ? ($dir === 'desc' ? '↓' : '↑') : '' }}
                        </a>
                    </th>
                    <th class="px-4 py-2 text-right">{{ __('Sales share %') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($itemSales as $row)
                    <tr>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-3 min-w-0">
                                @if ($row['image_url'])
                                    <img src="{{ $row['image_url'] }}" alt="" class="w-10 h-10 rounded-md object-cover shrink-0 bg-gray-100">
                                @else
                                    <div class="w-10 h-10 rounded-md bg-gray-100 shrink-0"></div>
                                @endif
                                <span class="text-gray-800 truncate">{{ $row['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['amount_sold']) }}</td>
                        <td class="px-4 py-2 text-right text-gray-700">GH₵{{ number_format($row['item_sales'] / 100, 2) }}</td>
                        <td class="px-4 py-2 text-right text-gray-500">{{ number_format($row['sales_share_pct'], 1) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No sales in this range.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($itemSales->hasPages())
        <div class="flex justify-center">
            {{ $itemSales->links() }}
        </div>
    @endif
</div>
