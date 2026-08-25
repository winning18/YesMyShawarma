<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">{{ $promotion->code }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.promotions.update', $promotion) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            @include('dashboard.promotions.partials.fields', ['promotion' => $promotion])

            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('dashboard.promotions.destroy', $promotion) }}" onsubmit="return confirm('{{ __('Remove this promotion?') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Remove promotion') }}</button>
        </form>
    </div>
</x-app-layout>
