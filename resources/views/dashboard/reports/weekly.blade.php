<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'weekly'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        <h3 class="font-semibold text-lg text-gray-800">{{ __('Weekly report') }}</h3>

        <form method="GET" action="{{ route('dashboard.reports.weekly.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1" for="week">{{ __('Week of') }}</label>
                <input
                    type="date" id="week" name="week" value="{{ $weekStart->toDateString() }}"
                    onchange="this.form.submit()"
                    class="rounded-md border-gray-300 text-sm"
                >
            </div>
            <span class="text-sm text-gray-500 pb-2">
                {{ $weekStart->format('d/m/Y') }} {{ __('to') }} {{ $weekEnd->format('d/m/Y') }}
            </span>
        </form>

        <a
            href="{{ route('dashboard.reports.weekly.download', ['week' => $weekStart->toDateString()]) }}"
            class="inline-block px-5 py-2.5 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
        >{{ __('Download CSV') }}</a>

        <p class="text-xs text-gray-400">
            {{ __('A row per order placed that week: reference, timestamp, customer, status, channel, fulfilment, and total.') }}
        </p>
    </div>
</x-app-layout>
