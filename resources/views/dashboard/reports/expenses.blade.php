<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'expenses'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
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
                'url' => request()->fullUrlWithQuery(['from' => $preset[1]->toDateString(), 'to' => $preset[2]->toDateString(), 'page' => null]),
                'active' => $from->isSameDay($preset[1]) && $to->isSameDay($preset[2]),
            ])->all();
            $filterParams = array_filter([
                'search' => $filters['search'] ?? null,
                'recorded_by' => $filters['recorded_by'] ?? null,
                'min_amount' => isset($filters['min_amount']) ? number_format($filters['min_amount'] / 100, 2, '.', '') : null,
                'max_amount' => isset($filters['max_amount']) ? number_format($filters['max_amount'] / 100, 2, '.', '') : null,
            ], fn ($value) => $value !== null);
            $downloadParams = [...$branchParam, ...$filterParams, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
            $hasActiveFilters = $filterParams !== [];
        @endphp

        <div>
            <h3 class="font-semibold text-lg text-gray-800">{{ __('Expenses') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Every expense recorded when a shift was closed. The same expenses are deducted in the sales report on Invoices and sales.') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-4">
            <form method="GET" action="{{ route('dashboard.reports.expenses.index') }}" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
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
                            <select id="branch" name="branch" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('All branches') }}</option>
                                @foreach ($branchOptions as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchFilterId === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label for="recorded_by" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Recorded by') }}</label>
                        <select id="recorded_by" name="recorded_by" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('Anyone') }}</option>
                            @foreach ($recordedByOptions as $person)
                                <option value="{{ $person->id }}" @selected(($filters['recorded_by'] ?? null) === $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="search" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Search') }}</label>
                        <input
                            type="text" id="search" name="search" value="{{ $filters['search'] ?? '' }}"
                            placeholder="{{ __('Description, e.g. gas…') }}"
                            class="w-full rounded-md border-gray-300 text-sm"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Amount (GH₵)') }}</label>
                        <div class="flex items-center gap-2">
                            <input
                                type="number" name="min_amount" min="0" step="0.01" inputmode="decimal"
                                value="{{ $filterParams['min_amount'] ?? '' }}" placeholder="{{ __('Min') }}"
                                aria-label="{{ __('Minimum amount') }}"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                            <span class="text-gray-400">–</span>
                            <input
                                type="number" name="max_amount" min="0" step="0.01" inputmode="decimal"
                                value="{{ $filterParams['max_amount'] ?? '' }}" placeholder="{{ __('Max') }}"
                                aria-label="{{ __('Maximum amount') }}"
                                class="w-full rounded-md border-gray-300 text-sm"
                            >
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Apply filters') }}
                    </button>
                    @if ($hasActiveFilters)
                        <a
                            href="{{ route('dashboard.reports.expenses.index', [...$branchParam, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                            class="text-sm text-gray-500 hover:text-gray-700 hover:underline"
                        >{{ __('Clear filters') }}</a>
                    @endif

                    <div class="flex flex-wrap items-center gap-2 ml-auto">
                        @foreach (['xlsx' => __('Download XLSX'), 'csv' => __('Download CSV'), 'pdf' => __('Download PDF')] as $format => $downloadLabel)
                            <a
                                href="{{ route('dashboard.reports.expenses.download', [...$downloadParams, 'format' => $format]) }}"
                                class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
                            >{{ $downloadLabel }}</a>
                        @endforeach
                    </div>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Total expenses') }}</p>
                <p class="text-xl font-semibold text-gray-800">GH₵{{ number_format($summary['total'] / 100, 2) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Entries') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $summary['count'] }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Shifts with expenses') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $summary['shifts_count'] }}</p>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Date') }}</th>
                        <th class="px-4 py-2">{{ __('Time') }}</th>
                        @if ($crossBranch && ! $branchFilterId)
                            <th class="px-4 py-2">{{ __('Branch') }}</th>
                        @endif
                        <th class="px-4 py-2">{{ __('Recorded by') }}</th>
                        <th class="px-4 py-2">{{ __('Description') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($expense['date'])->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $expense['time'] }}</td>
                            @if ($crossBranch && ! $branchFilterId)
                                <td class="px-4 py-2 text-gray-500">{{ $expense['branch'] }}</td>
                            @endif
                            <td class="px-4 py-2 text-gray-500">{{ $expense['recorded_by'] }}</td>
                            <td class="px-4 py-2 text-gray-800">{{ $expense['description'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-800 whitespace-nowrap">GH₵{{ number_format($expense['amount'] / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                {{ $hasActiveFilters ? __('No expenses match these filters.') : __('No expenses recorded in these dates.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($summary['count'] > 0)
                    <tfoot class="bg-gray-50 font-semibold text-gray-800">
                        <tr>
                            <td class="px-4 py-2" colspan="{{ $crossBranch && ! $branchFilterId ? 5 : 4 }}">
                                {{ __('Total') }} <span class="font-normal text-gray-500">({{ trans_choice(':count entry|:count entries', $summary['count'], ['count' => $summary['count']]) }})</span>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">GH₵{{ number_format($summary['total'] / 100, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{ $expenses->links() }}
    </div>
</x-app-layout>
