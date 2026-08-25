<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'invoices'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-8">
        <section class="space-y-4">
            <div>
                <h3 class="font-semibold text-gray-800">{{ __('Sales report') }}</h3>
                <p class="text-sm text-gray-500">{{ __('Download a report of all your sales for the selected week.') }}</p>
            </div>

            <div class="flex items-center gap-3">
                <a
                    href="{{ route('dashboard.reports.invoices.index') }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $isThisWeek ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('This week') }}</a>
                <a
                    href="{{ route('dashboard.reports.invoices.index', ['week' => now('Africa/Accra')->subWeek()->toDateString()]) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $isLastWeek ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('Last week') }}</a>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Chosen dates') }}</label>
                <div class="flex flex-wrap items-center gap-3">
                    <form method="GET" action="{{ route('dashboard.reports.invoices.index') }}" class="flex items-center gap-2">
                        <input
                            type="date" name="week" value="{{ $weekStart->toDateString() }}"
                            onchange="this.form.submit()"
                            class="rounded-md border-gray-300 text-sm"
                        >
                        <span class="text-sm text-gray-500">
                            {{ $weekStart->format('d/m') }} - {{ $weekEnd->format('d/m') }} ({{ __('Week :week of :year', ['week' => $weekStart->isoWeek(), 'year' => $weekStart->isoWeekYear()]) }})
                        </span>
                    </form>

                    <div class="flex items-center gap-2 ml-auto">
                        <a
                            href="{{ route('dashboard.reports.invoices.download', ['format' => 'xlsx', 'week' => $weekStart->toDateString()]) }}"
                            class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
                        >{{ __('Download XLSX') }}</a>
                        <a
                            href="{{ route('dashboard.reports.invoices.download', ['format' => 'csv', 'week' => $weekStart->toDateString()]) }}"
                            class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
                        >{{ __('Download CSV') }}</a>
                        <a
                            href="{{ route('dashboard.reports.invoices.download', ['format' => 'pdf', 'week' => $weekStart->toDateString()]) }}"
                            class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
                        >{{ __('Download PDF') }}</a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Orders') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $summary['orders_count'] }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Total sales') }}</p>
                    <p class="text-xl font-semibold text-gray-800">GH₵{{ number_format($summary['total'] / 100, 2) }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('City') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $summary['city'] }}</p>
                </div>
            </div>
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
                            <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
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
                                        href="{{ route('dashboard.reports.invoices.download', ['format' => 'csv', 'week' => $week['start']->toDateString()]) }}"
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
