<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item->name }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow rounded-lg p-6 flex items-center justify-between gap-4">
            <div>
                <p class="text-xs text-gray-500">{{ __('Current quantity') }}</p>
                <p class="text-2xl font-semibold {{ $item->isLowStock() ? 'text-red-600' : 'text-gray-800' }}">
                    {{ $item->quantity }} {{ $item->unit }}
                </p>
                @if ($item->isLowStock())
                    <p class="text-xs text-red-600 mt-1">{{ __('Below the low-stock reminder limit.') }}</p>
                @endif
            </div>
            <a href="{{ route('dashboard.stock.history', $item) }}" class="text-sm text-gray-600 hover:underline shrink-0">
                {{ __('View history') }}
            </a>
        </div>

        <form method="POST" action="{{ route('dashboard.stock.restock', $item) }}" class="bg-white shadow rounded-lg p-6 space-y-4">
            @csrf
            <h3 class="font-semibold text-gray-800">{{ __('Restock') }}</h3>
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label for="restock_quantity" :value="__('Quantity to add')" required />
                    <x-text-input id="restock_quantity" name="quantity" type="number" step="0.01" min="0.01" class="mt-1 block w-full" required />
                </div>
                <x-primary-button>{{ __('Restock') }}</x-primary-button>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
            <div>
                <x-input-label for="restock_note" :value="__('Note (optional)')" />
                <x-text-input id="restock_note" name="note" type="text" class="mt-1 block w-full" />
            </div>
        </form>

        <form method="POST" action="{{ route('dashboard.stock.update', $item) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            @include('dashboard.stock.partials.fields', ['item' => $item])

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                <a href="{{ route('dashboard.stock.index') }}" class="text-sm text-gray-600 hover:underline">
                    {{ __('Back to stock') }}
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
