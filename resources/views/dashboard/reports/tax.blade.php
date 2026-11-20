<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'tax'])
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        @php
            $money = fn (int $pesewas) => 'GH₵'.number_format($pesewas / 100, 2);
            $branchParam = $branchFilterId ? ['branch' => $branchFilterId] : [];
            $now = now('Africa/Accra');
            $rangePresets = [
                [__('This month'), $now->clone()->startOfMonth(), $now->clone()->endOfMonth()],
                [__('Last month'), $now->clone()->subMonthNoOverflow()->startOfMonth(), $now->clone()->subMonthNoOverflow()->endOfMonth()],
                [__('This year'), $now->clone()->startOfYear(), $now->clone()->endOfYear()],
            ];
            $dateRangePresetLinks = collect($rangePresets)->map(fn ($preset) => [
                'label' => $preset[0],
                'url' => request()->fullUrlWithQuery(['from' => $preset[1]->toDateString(), 'to' => $preset[2]->toDateString()]),
                'active' => $from->isSameDay($preset[1]) && $to->isSameDay($preset[2]),
            ])->all();
        @endphp

        <div>
            <h3 class="font-semibold text-lg text-gray-800">{{ __('Tax') }}</h3>
            <p class="text-sm text-gray-500">{{ __('The tax charged on orders placed in these dates, less the tax on refunds completed in them. Bolt Food orders and freebies carry no tax.') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-4">
            <form method="GET" action="{{ route('dashboard.reports.tax.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Dates') }}</label>
                    <x-date-range-picker
                        :from="$from->toDateString()"
                        :to="$to->toDateString()"
                        :label="$from->format('d/m/Y').' - '.$to->format('d/m/Y')"
                        :preset-links="$dateRangePresetLinks"
                    />
                </div>

                @if ($crossBranch)
                    <div>
                        <label for="branch" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Branch') }}</label>
                        <select id="branch" name="branch" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ $allBranchesLabel }}</option>
                            @foreach ($branchOptions as $branch)
                                <option value="{{ $branch->id }}" @selected($branchFilterId === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Apply') }}</button>

                <a href="{{ route('dashboard.reports.tax.download', [...$branchParam, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                    class="ml-auto px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800">{{ __('Download CSV') }}</a>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Sales before tax') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $money($report['taxable']) }}</p>
                <p class="text-xs text-gray-400">{{ trans_choice(':count taxed order|:count taxed orders', $report['orders_count'], ['count' => $report['orders_count']]) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Tax charged') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $money($report['tax_total']) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Less tax on refunds') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $report['refunded_tax'] ? '−'.$money($report['refunded_tax']) : $money(0) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Tax due') }}</p>
                <p class="text-xl font-semibold text-green-700">{{ $money($report['due']) }}</p>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Tax') }}</th>
                        <th class="px-4 py-2">{{ __('Prices') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('On food') }}</th>
                        @module('delivery')
                        <th class="px-4 py-2 text-right">{{ __('On delivery') }}</th>
                        @endmodule
                        <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($report['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2 text-gray-800">{{ $line['label'] }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $line['inclusive'] ? __('Included in prices') : __('Added on top') }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($line['food']) }}</td>
                            @module('delivery')
                            <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($line['delivery']) }}</td>
                            @endmodule
                            <td class="px-4 py-2 text-right whitespace-nowrap font-semibold">{{ $money($line['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No tax charged in these dates.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($report['days'] !== [])
            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Date') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Orders') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Sales before tax') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Tax') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($report['days'] as $day)
                            <tr>
                                <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D d/m/Y') }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $day['orders'] }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($day['sales']) }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($day['tax']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
