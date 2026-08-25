<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Option groups') }}</h2>
            <a href="{{ route('dashboard.option-groups.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                {{ __('Add option group') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        @forelse ($optionGroups as $group)
            @if ($loop->first)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @endif

            <div class="bg-white shadow rounded-lg p-4 flex flex-col gap-2">
                <p class="font-semibold text-gray-800">
                    {{ $group->name }}
                    @if ($group->is_required)
                        <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 align-middle">{{ __('Required') }}</span>
                    @endif
                </p>
                <p class="text-sm text-gray-500">
                    {{ __('Select :min–:max', ['min' => $group->min_select, 'max' => $group->max_select]) }}
                    · {{ $group->options_count }} {{ __('options') }}
                </p>
                <a href="{{ route('dashboard.option-groups.edit', $group) }}" class="text-sm text-gray-600 hover:underline">
                    {{ __('Edit') }}
                </a>
            </div>

            @if ($loop->last)
                </div>
            @endif
        @empty
            <div class="bg-white shadow rounded-lg p-6 text-center text-sm text-gray-500">{{ __('No option groups yet.') }}</div>
        @endforelse
    </div>
</x-app-layout>
