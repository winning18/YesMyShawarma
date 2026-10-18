<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'invoices'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-8">
        <section class="space-y-4">
            <div>
                <h3 class="font-semibold text-gray-800">{{ __('Sales report') }}</h3>
                <p class="text-sm text-gray-500">{{ __('Download a statement of every order in the selected dates, grouped by day, with refunds and expenses deducted. Bolt Food orders have their own report.') }}</p>
            </div>

            @php
                $branchParam = $branchFilterId ? ['branch' => $branchFilterId] : [];
                $now = now('Africa/Accra');
                $rangePresets = [
                    [__('This week'), $now->clone()->startOfWeek(), $now->clone()->endOfWeek()],
                    [__('Last week'), $now->clone()->subWeek()->startOfWeek(), $now->clone()->subWeek()->endOfWeek()],
                    [__('This month'), $now->clone()->startOfMonth(), $now->clone()->endOfMonth()],
                    [__('Last month'), $now->clone()->subMonthNoOverflow()->startOfMonth(), $now->clone()->subMonthNoOverflow()->endOfMonth()],
                ];
                $dateRangePresetLinks = collect($rangePresets)->map(fn ($preset) => [
                    'label' => $preset[0],
                    'url' => route('dashboard.reports.invoices.index', [...$branchParam, 'type' => $type, 'from' => $preset[1]->toDateString(), 'to' => $preset[2]->toDateString()]),
                    'active' => $from->isSameDay($preset[1]) && $to->isSameDay($preset[2]),
                ])->all();
                $downloadParams = [...$branchParam, 'type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
                $isSalesReport = $type === \App\Services\Reports\SalesStatementService::TYPE_SALES;
            @endphp

            <div class="flex items-center gap-3">
                <a
                    href="{{ route('dashboard.reports.invoices.index', [...$branchParam, 'type' => $type]) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $isThisWeek ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('This week') }}</a>
                <a
                    href="{{ route('dashboard.reports.invoices.index', [...$branchParam, 'type' => $type, 'week' => $now->clone()->subWeek()->toDateString()]) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $isLastWeek ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('Last week') }}</a>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <form method="GET" action="{{ route('dashboard.reports.invoices.index') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Chosen dates') }}</label>
                        <x-date-range-picker
                            :from="$from->toDateString()"
                            :to="$to->toDateString()"
                            :label="$from->format('d/m/Y').' - '.$to->format('d/m/Y')"
                            :preset-links="$dateRangePresetLinks"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1" for="type">{{ __('Report') }}</label>
                        <select id="type" name="type" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                            <option value="sales" @selected($isSalesReport)>{{ __('Sales') }}</option>
                            <option value="bolt_food" @selected(! $isSalesReport)>{{ __('Bolt Food') }}</option>
                        </select>
                    </div>
                    @if ($crossBranch)
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1" for="branch">{{ __('Branch') }}</label>
                            <select id="branch" name="branch" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('All branches') }}</option>
                                @foreach ($branchOptions as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchFilterId === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </form>

                <div class="flex flex-wrap items-center gap-2 ml-auto">
                    @foreach (['xlsx' => __('Download XLSX'), 'csv' => __('Download CSV'), 'pdf' => __('Download PDF')] as $format => $downloadLabel)
                        <a
                            href="{{ route('dashboard.reports.invoices.download', [...$downloadParams, 'format' => $format]) }}"
                            class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
                        >{{ $downloadLabel }}</a>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-2 {{ $isSalesReport ? 'sm:grid-cols-4' : 'sm:grid-cols-2' }} gap-4">
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Orders') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $report['orders_count'] }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ $isSalesReport ? __('Total sales') : __('Bolt Food sales') }}</p>
                    <p class="text-xl font-semibold text-gray-800">GH₵{{ number_format($report['sales_total'] / 100, 2) }}</p>
                </div>
                @if ($isSalesReport)
                    <div class="bg-white shadow rounded-lg p-4">
                        <p class="text-xs text-gray-500">{{ __('Refunds + expenses') }}</p>
                        <p class="text-xl font-semibold text-gray-800">−GH₵{{ number_format(($report['refunds_total'] + $report['expenses_total']) / 100, 2) }}</p>
                    </div>
                    <div class="bg-white shadow rounded-lg p-4">
                        <p class="text-xs text-gray-500">{{ __('Net sales') }}</p>
                        <p class="text-xl font-semibold {{ $report['net_total'] < 0 ? 'text-brand-red' : 'text-gray-800' }}">GH₵{{ number_format($report['net_total'] / 100, 2) }}</p>
                    </div>
                @endif
            </div>
            <p class="text-xs text-gray-500">
                {{ __('Not counted in sales') }}:
                {{ collect($report['excluded_counts'])->map(fn ($count, $status) => __($status).': '.$count)->implode(' · ') }}
            </p>
        </section>

        <section class="space-y-4">
            <h3 class="font-semibold text-gray-800">{{ __('Weekly invoices') }}</h3>

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Start') }}</th>
                            <th class="px-4 py-2">{{ __('End') }}</th>
                            <th class="px-4 py-2">{{ __('City') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Orders') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Sales') }}</th>
                            <th class="px-4 py-2">{{ __('Currency') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($history as $week)
                            <tr>
                                <td class="px-4 py-2 text-gray-800">{{ $week['start']->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-gray-800">{{ $week['end']->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $week['city'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $week['orders_count'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-800">{{ number_format($week['total'] / 100, 2) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $week['currency'] }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a
                                        href="{{ route('dashboard.reports.invoices.download', [...$branchParam, 'format' => 'csv', 'week' => $week['start']->toDateString()]) }}"
                                        class="text-gray-400 hover:text-gray-700"
                                        title="{{ __('Download CSV') }}"
                                        aria-label="{{ __('Download CSV for the week of :date', ['date' => $week['start']->format('d/m/Y')]) }}"
                                    >
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 inline">
                                            <path d="M10 12.5a.75.75 0 0 1-.53-.22l-3.5-3.5a.75.75 0 1 1 1.06-1.06L9.25 9.94V3a.75.75 0 0 1 1.5 0v6.94l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-.53.22Z" />
                                            <path d="M4 13a.75.75 0 0 1 .75.75v1.5c0 .414.336.75.75.75h9a.75.75 0 0 0 .75-.75v-1.5a.75.75 0 0 1 1.5 0v1.5A2.25 2.25 0 0 1 14.5 17.5h-9A2.25 2.25 0 0 1 3.25 15.25v-1.5A.75.75 0 0 1 4 13Z" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No sales history yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $history->links() }}
        </section>
    </div>
</x-app-layout>
