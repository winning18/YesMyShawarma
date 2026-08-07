<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Promotions') }}</h2>
            <a href="{{ route('dashboard.promotions.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                {{ __('Add promotion') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        @forelse ($promotions as $promotion)
            @if ($loop->first)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @endif

            <div class="bg-white shadow rounded-lg p-4 flex flex-col gap-2">
                <p class="font-semibold text-gray-800 font-mono">
                    {{ $promotion->code }}
                    @unless ($promotion->is_active)
                        <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 align-middle font-sans">{{ __('Inactive') }}</span>
                    @endunless
                </p>
                <p class="text-sm text-gray-500">
                    {{ $promotion->type === 'percentage' ? $promotion->value.'% off' : 'GH₵'.number_format($promotion->value / 100, 2).' off' }}
                    · {{ __(':count uses', ['count' => $promotion->redemptions_count]) }}
                </p>
                <a href="{{ route('dashboard.promotions.edit', $promotion) }}" class="text-sm text-gray-600 hover:underline">
                    {{ __('Edit') }}
                </a>
            </div>

            @if ($loop->last)
                </div>
            @endif
        @empty
            <div class="bg-white shadow rounded-lg p-6 text-center text-sm text-gray-500">{{ __('No promotions yet.') }}</div>
        @endforelse
    </div>
</x-app-layout>
