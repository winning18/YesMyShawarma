<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Help') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        <p class="text-sm text-gray-500">{{ __('Short guides for this system. Each one can be printed and kept at the counter.') }}</p>

        @foreach ($guides as $key => $guide)
            <a href="{{ route('dashboard.help.show', $key) }}" class="block bg-white shadow rounded-lg p-5 hover:bg-gray-50">
                <p class="font-semibold text-gray-800">{{ __($guide['title']) }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __($guide['summary']) }}</p>
            </a>
        @endforeach
    </div>
</x-app-layout>
