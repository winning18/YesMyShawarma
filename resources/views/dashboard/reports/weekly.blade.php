<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'weekly'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        @php
            $branchParam = $branchFilterId ? ['branch' => $branchFilterId] : [];
        @endphp

        <div>
            <h3 class="font-semibold text-lg text-gray-800">{{ __('Order export') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Every order placed in the chosen week, for checking and auditing. This is not a sales report: it includes cancelled, failed and Bolt Food orders. For sales figures, use Invoices and sales.') }}</p>
        </div>

        <form method="GET" action="{{ route('dashboard.reports.weekly.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Week of') }}</label>
                <x-date-range-picker
                    single
                    field-name="week"
                    :from="$weekStart->toDateString()"
                    :label="$weekStart->format('d/m/Y').' '.__('to').' '.$weekEnd->format('d/m/Y')"
                />
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

        <a
            href="{{ route('dashboard.reports.weekly.download', [...$branchParam, 'week' => $weekStart->toDateString()]) }}"
            class="inline-block px-5 py-2.5 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
        >{{ __('Download order export (CSV)') }}</a>

        <p class="text-xs text-gray-400">
            {{ __('A row per order placed that week: reference, timestamp, customer, status, channel, fulfilment, and total.') }}
        </p>
    </div>
</x-app-layout>
