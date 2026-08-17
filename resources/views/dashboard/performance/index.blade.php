<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Performance') }}</h2>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        <div class="border-b border-gray-200 flex items-center gap-6">
            <a
                href="{{ route('dashboard.performance', ['tab' => 'sales', 'range' => $rangeKey]) }}"
                class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $tab === 'sales' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >{{ __('Sales') }}</a>
            <a
                href="{{ route('dashboard.performance', ['tab' => 'operations', 'range' => $rangeKey]) }}"
                class="pb-3 text-sm font-semibold border-b-2 -mb-px {{ $tab === 'operations' ? 'border-green-600 text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >{{ __('Operations') }}</a>
        </div>

        <form method="GET" action="{{ route('dashboard.performance') }}" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="range" class="sr-only">{{ __('Date range') }}</label>
            <select id="range" name="range" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                <option value="today" @selected($rangeKey === 'today')>{{ __('Today') }}</option>
                <option value="7" @selected($rangeKey === '7')>{{ __('Last 7 days') }}</option>
                <option value="30" @selected($rangeKey === '30')>{{ __('Last 30 days') }}</option>
            </select>

            @if ($tab === 'operations' && $crossBranch)
                <label for="branch" class="sr-only">{{ __('Branch') }}</label>
                <select id="branch" name="branch" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch->id }}" @selected($branchFilterId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
        </form>

        @if ($tab === 'sales')
            @include('dashboard.performance.partials.sales')
        @else
            @include('dashboard.performance.partials.operations')
        @endif
    </div>
</x-app-layout>
