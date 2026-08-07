<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Categories') }}</h2>
            <a href="{{ route('dashboard.categories.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                {{ __('Add category') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach ($categories as $category)
                <div class="bg-white shadow rounded-lg p-4 flex flex-col gap-2">
                    <p class="font-semibold text-gray-800">{{ $category->name }}</p>
                    <p class="text-sm text-gray-500">{{ __('Sort order') }}: {{ $category->sort_order }} · {{ $category->menuItems()->count() }} {{ __('items') }}</p>
                    <a href="{{ route('dashboard.categories.edit', $category) }}" class="text-sm text-gray-600 hover:underline">
                        {{ __('Edit') }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
