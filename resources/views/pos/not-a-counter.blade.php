<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('POS') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-12 px-4">
        <div class="bg-white shadow rounded-lg p-6 space-y-3 text-center">
            <h3 class="font-semibold text-gray-800">{{ __('This device isn\'t a counter') }}</h3>
            <p class="text-sm text-gray-600">{{ __(':branch takes POS sales only on its counters, so every sale lands in a counter\'s till.', ['branch' => $branch->name]) }}</p>
            @if ($canSetUp)
                <a href="{{ route('dashboard.counters.index') }}" class="inline-block mt-2 px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Set this device up as a counter') }}</a>
            @else
                <p class="text-sm text-gray-600">{{ __('Ask a manager to set this device up as a counter.') }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
