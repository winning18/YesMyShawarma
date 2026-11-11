<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'profit'])
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        @php
            $money = fn (int $pesewas) => 'GH₵'.number_format($pesewas / 100, 2);
            $branchParam = $branchFilterId ? ['branch' => $branchFilterId] : [];
            $now = now('Africa/Accra');
            $rangePresets = [
                [__('This week'), $now->clone()->startOfWeek(), $now->clone()->endOfWeek()],
                [__('This month'), $now->clone()->startOfMonth(), $now->clone()->endOfMonth()],
                [__('Last month'), $now->clone()->subMonthNoOverflow()->startOfMonth(), $now->clone()->subMonthNoOverflow()->endOfMonth()],
                [__('This year'), $now->clone()->startOfYear(), $now->clone()->endOfYear()],
            ];
            $dateRangePresetLinks = collect($rangePresets)->map(fn ($preset) => [
                'label' => $preset[0],
                'url' => request()->fullUrlWithQuery(['from' => $preset[1]->toDateString(), 'to' => $preset[2]->toDateString()]),
                'active' => $from->isSameDay($preset[1]) && $to->isSameDay($preset[2]),
            ])->all();
            $downloadParams = [...$branchParam, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'group_by' => $groupBy];
            $totals = $report['totals'];
        @endphp

        <div>
            <h3 class="font-semibold text-lg text-gray-800">{{ __('Profit') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Sales minus approved purchases and expenses from the market list. Only as complete as what gets recorded: rent, salaries or bills count only if they\'re on the market list.') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-4">
            <form method="GET" action="{{ route('dashboard.reports.profit.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Dates') }}</label>
                    <x-date-range-picker
                        :from="$from->toDateString()"
                        :to="$to->toDateString()"
                        :label="$from->format('d/m/Y').' - '.$to->format('d/m/Y')"
                        :preset-links="$dateRangePresetLinks"
                    />
                </div>

                <div>
                    <label for="group_by" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Show per') }}</label>
                    <select id="group_by" name="group_by" class="rounded-md border-gray-300 text-sm">
                        @foreach (['day' => __('Day'), 'week' => __('Week'), 'month' => __('Month'), 'year' => __('Year')] as $value => $label)
                            <option value="{{ $value }}" @selected($groupBy === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
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

                <div class="flex flex-wrap items-center gap-2 ml-auto">
                    @foreach (['xlsx' => __('Download XLSX'), 'csv' => __('Download CSV'), 'pdf' => __('Download PDF')] as $format => $downloadLabel)
                        <a href="{{ route('dashboard.reports.profit.download', [...$downloadParams, 'format' => $format]) }}"
                            class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800">{{ $downloadLabel }}</a>
                    @endforeach
                </div>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Sales (after refunds)') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $money($totals['sales'] - $totals['refunds']) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Purchases & expenses') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $money($totals['purchases']) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Profit') }}</p>
                <p class="text-xl font-semibold {{ $totals['profit'] < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $money($totals['profit']) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Profit incl. Bolt Food') }}</p>
                <p class="text-xl font-semibold {{ $totals['profit_with_bolt'] < 0 ? 'text-red-600' : 'text-gray-800' }}">{{ $money($totals['profit_with_bolt']) }}</p>
                <p class="text-xs text-gray-400">{{ __('Bolt Food :amount, before Bolt\'s commission', ['amount' => $money($totals['bolt_food'])]) }}</p>
            </div>
        </div>

        @if ($report['awaiting']['count'] > 0)
            <div class="rounded-md bg-amber-50 text-amber-800 text-sm px-4 py-2">
                {{ trans_choice(':count market list (:amount) in these dates is still waiting for approval and isn\'t counted yet.|:count market lists (:amount) in these dates are still waiting for approval and aren\'t counted yet.', $report['awaiting']['count'], ['count' => $report['awaiting']['count'], 'amount' => $money($report['awaiting']['total'])]) }}
                <a href="{{ route('dashboard.market-lists.index', ['status' => 'pending']) }}" class="underline font-semibold">{{ __('Review') }}</a>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Period') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Sales') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Refunds') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Purchases & expenses') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Profit') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Bolt Food') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Profit incl. Bolt') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($report['periods'] as $period)
                        <tr>
                            <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ $period['label'] }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($period['sales']) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap text-gray-500">{{ $period['refunds'] ? '−'.$money($period['refunds']) : '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap text-gray-500">{{ $period['purchases'] ? '−'.$money($period['purchases']) : '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap font-semibold {{ $period['profit'] < 0 ? 'text-red-600' : 'text-gray-800' }}">{{ $money($period['profit']) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap text-gray-500">{{ $money($period['bolt_food']) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap {{ $period['profit_with_bolt'] < 0 ? 'text-red-600' : 'text-gray-800' }}">{{ $money($period['profit_with_bolt']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-gray-800">
                    <tr>
                        <td class="px-4 py-2">{{ __('Total') }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($totals['sales']) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">−{{ $money($totals['refunds']) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">−{{ $money($totals['purchases']) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap {{ $totals['profit'] < 0 ? 'text-red-600' : '' }}">{{ $money($totals['profit']) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($totals['bolt_food']) }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap {{ $totals['profit_with_bolt'] < 0 ? 'text-red-600' : '' }}">{{ $money($totals['profit_with_bolt']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
            <div class="px-4 pt-4">
                <h4 class="font-semibold text-gray-800">{{ __('Purchases by kind') }}</h4>
                <p class="text-xs text-gray-500">{{ __('Everything of each kind bought in these dates, approved only.') }}</p>
            </div>
            <table class="w-full text-sm mt-2">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Kind') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Purchases') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Quantity') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Share') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($report['by_kind'] as $kind)
                        <tr>
                            <td class="px-4 py-2 text-gray-800">{{ $kind['kind'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $kind['count'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $kind['quantity'] !== null ? rtrim(rtrim(number_format($kind['quantity'], 2), '0'), '.').' '.$kind['unit'] : '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($kind['total']) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $totals['purchases'] > 0 ? round($kind['total'] / $totals['purchases'] * 100).'%' : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No approved purchases in these dates.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
