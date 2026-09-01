<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Record a sale') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            @error('quantity')
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>
            @enderror

            @forelse ($items as $item)
                <div class="bg-white shadow rounded-lg p-4 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800">{{ $item->name }}</p>
                        <p class="text-sm text-gray-500">
                            {{ __(':quantity :unit in stock', ['quantity' => $item->quantity, 'unit' => $item->unit]) }}
                            @if ($item->isLowStock())
                                <span class="text-xs px-2 py-0.5 rounded-md bg-red-50 text-red-700 align-middle">{{ __('Low stock') }}</span>
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('dashboard.stock.sales.store', $item) }}" class="flex items-center gap-2 shrink-0">
                        @csrf
                        <input
                            type="number" step="0.01" min="0.01" name="quantity" required
                            placeholder="{{ __('Qty') }}"
                            class="w-24 rounded-md border-gray-300 text-sm"
                        >
                        <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                            {{ __('Record sale') }}
                        </button>
                    </form>
                </div>
            @empty
                <div class="bg-white shadow rounded-lg p-6 text-center text-gray-500">
                    {{ __('No stock items have been added yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
