<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $menuItem->name }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        <div class="flex items-center justify-between gap-3 text-sm">
            @if ($previousItem)
                <a
                    href="{{ route('dashboard.menu-items.edit', $previousItem) }}"
                    class="inline-flex items-center gap-1 max-w-[45%] text-gray-600 hover:text-gray-900"
                    title="{{ $previousItem->name }}"
                    aria-label="{{ __('Previous item') }}"
                >
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="none"><path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span class="truncate">{{ $previousItem->name }}</span>
                </a>
            @else
                <span></span>
            @endif

            @if ($nextItem)
                <a
                    href="{{ route('dashboard.menu-items.edit', $nextItem) }}"
                    class="inline-flex items-center gap-1 max-w-[45%] text-gray-600 hover:text-gray-900"
                    title="{{ $nextItem->name }}"
                    aria-label="{{ __('Next item') }}"
                >
                    <span class="truncate">{{ $nextItem->name }}</span>
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="none"><path d="M7.5 5L12.5 10L7.5 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </a>
            @else
                <span></span>
            @endif
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.menu-items.update', $menuItem) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            @include('dashboard.menu-items.partials.fields', ['menuItem' => $menuItem])

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                <a href="{{ route('dashboard.menu-items.index') }}" class="text-sm text-gray-600 hover:underline">
                    {{ __('Back to menu') }}
                </a>
            </div>
        </form>

        <div class="bg-white shadow rounded-lg p-6 flex items-start gap-6">
            <x-product-image :item="$menuItem" class="w-24 h-24 rounded-md shrink-0" />

            <div class="flex-1 min-w-0 space-y-3">
                <form
                    method="POST"
                    action="{{ route('dashboard.menu-items.image.update', $menuItem) }}"
                    enctype="multipart/form-data"
                    class="flex items-center gap-3"
                >
                    @csrf
                    <input type="file" name="image" accept="image/*" required class="text-sm">
                    <button type="submit" class="shrink-0 px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Upload') }}
                    </button>
                </form>
                @error('image')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                @if ($menuItem->image_path)
                    <form method="POST" action="{{ route('dashboard.menu-items.image.destroy', $menuItem) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Remove image') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6 space-y-4">
            <div>
                <h3 class="font-semibold text-gray-800">{{ __('Recorded as (daily sales reporting)') }}</h3>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('If this item is a combo (e.g. "Signature" = Chicken Shawarma + Cheese + Sausage), configure what it counts as below. A plain item with nothing configured here is recorded under its own name as usual.') }}
                </p>
            </div>

            <div>
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('Base items') }}</h4>
                @forelse ($components->where('component_type', 'base') as $component)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0">
                        <span class="text-sm text-gray-800">{{ $component->quantity }}× {{ $component->componentMenuItem->name }}</span>
                        <form
                            method="POST" action="{{ route('dashboard.menu-items.components.destroy', [$menuItem, $component]) }}"
                            onsubmit="return confirm('{{ __('Remove this?') }}')"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('None configured — this item is recorded under its own name.') }}</p>
                @endforelse

                <form method="POST" action="{{ route('dashboard.menu-items.components.store', $menuItem) }}" class="flex items-center gap-2 mt-3">
                    @csrf
                    <input type="hidden" name="component_type" value="base">
                    <select name="component_menu_item_id" class="flex-1 rounded-md border-gray-300 text-sm" required>
                        <option value="">{{ __('Select item…') }}</option>
                        @foreach ($baseItemChoices as $choice)
                            <option value="{{ $choice->id }}">{{ $choice->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="quantity" value="1" min="1" max="20" class="w-16 rounded-md border-gray-300 text-sm">
                    <button type="submit" class="shrink-0 px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Add') }}
                    </button>
                </form>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('Modifiers') }}</h4>
                @forelse ($components->where('component_type', 'modifier') as $component)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0">
                        <span class="text-sm text-gray-800">{{ $component->quantity }}× {{ $component->componentOption->name }}</span>
                        <form
                            method="POST" action="{{ route('dashboard.menu-items.components.destroy', [$menuItem, $component]) }}"
                            onsubmit="return confirm('{{ __('Remove this?') }}')"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('None configured.') }}</p>
                @endforelse

                <form method="POST" action="{{ route('dashboard.menu-items.components.store', $menuItem) }}" class="flex items-center gap-2 mt-3">
                    @csrf
                    <input type="hidden" name="component_type" value="modifier">
                    <select name="component_option_id" class="flex-1 rounded-md border-gray-300 text-sm" required>
                        <option value="">{{ __('Select modifier…') }}</option>
                        @foreach ($modifierChoices as $choice)
                            <option value="{{ $choice->id }}">{{ $choice->optionGroup->name }} — {{ $choice->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="quantity" value="1" min="1" max="20" class="w-16 rounded-md border-gray-300 text-sm">
                    <button type="submit" class="shrink-0 px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Add') }}
                    </button>
                </form>
            </div>

            @error('component_menu_item_id')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('component_option_id')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
            @error('quantity')
                <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <form
            method="POST" action="{{ route('dashboard.menu-items.destroy', $menuItem) }}"
            onsubmit="return confirm('{{ __('Remove :name from the menu?', ['name' => $menuItem->name]) }}')"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Delete this item') }}</button>
        </form>
    </div>
</x-app-layout>
