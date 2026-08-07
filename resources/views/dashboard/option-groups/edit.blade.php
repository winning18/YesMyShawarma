<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $optionGroup->name }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.option-groups.update', $optionGroup) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            @include('dashboard.option-groups.partials.fields', ['optionGroup' => $optionGroup])

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                <a href="{{ route('dashboard.option-groups.index') }}" class="text-sm text-gray-600 hover:underline">
                    {{ __('Back to option groups') }}
                </a>
            </div>
        </form>

        <div class="bg-white shadow rounded-lg p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">{{ __('Options') }}</h3>

            @forelse ($options as $option)
                <form
                    method="POST" action="{{ route('dashboard.option-groups.options.update', [$optionGroup, $option]) }}"
                    class="flex items-center gap-3 border-b border-gray-100 pb-4 last:border-0 last:pb-0"
                >
                    @csrf
                    @method('PUT')
                    <input type="text" name="name" value="{{ old('name', $option->name) }}" class="flex-1 rounded-md border-gray-300 text-sm" required>
                    <input
                        type="number" step="0.01" min="0" name="price_delta"
                        value="{{ old('price_delta', number_format($option->price_delta / 100, 2, '.', '')) }}"
                        class="w-24 rounded-md border-gray-300 text-sm"
                    >
                    <label class="flex items-center gap-1.5 text-xs text-gray-500 shrink-0">
                        <input type="checkbox" name="is_active" value="1" @checked($option->is_active) class="rounded border-gray-300">
                        {{ __('Active') }}
                    </label>
                    <button type="submit" class="shrink-0 px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Save') }}
                    </button>
                </form>
                <form
                    method="POST" action="{{ route('dashboard.option-groups.options.destroy', [$optionGroup, $option]) }}"
                    class="-mt-3 pb-1"
                    onsubmit="return confirm('{{ __('Remove :name?', ['name' => $option->name]) }}')"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button>
                </form>
            @empty
                <p class="text-sm text-gray-500">{{ __('No options in this group yet.') }}</p>
            @endforelse

            <form method="POST" action="{{ route('dashboard.option-groups.options.store', $optionGroup) }}" class="flex items-center gap-3 border-t border-gray-100 pt-4">
                @csrf
                <input type="text" name="name" placeholder="{{ __('New option name') }}" class="flex-1 rounded-md border-gray-300 text-sm" required>
                <input type="number" step="0.01" min="0" name="price_delta" placeholder="0.00" class="w-24 rounded-md border-gray-300 text-sm" required>
                <button type="submit" class="shrink-0 px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">
                    {{ __('Add') }}
                </button>
            </form>
            @error('name')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('price_delta')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</x-app-layout>
