<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Menu') }}</h2>
                <p class="text-sm text-gray-500">{{ $branch->name }}</p>
            </div>
            @can('menu.edit_content')
                <a href="{{ route('dashboard.menu-items.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                    {{ __('Add item') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-8">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        @forelse ($categories as $group)
            <section class="space-y-4">
                <h3 class="font-semibold text-gray-800">{{ $group['category']->name }}</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($group['items'] as $row)
                        @php $item = $row['item']; $pivot = $row['pivot']; @endphp
                        <div class="bg-white shadow rounded-lg p-4 flex flex-col gap-3">
                            <x-product-image :item="$item" class="w-full rounded-md" />

                            <div class="flex-1 min-w-0 space-y-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-800">
                                        {{ $item->name }}
                                        @unless ($item->is_active)
                                            <span class="text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 align-middle">{{ __('Inactive') }}</span>
                                        @endunless
                                    </p>
                                    @if ($item->description)
                                        <p class="text-sm text-gray-500">{{ Str::limit($item->description, 60) }}</p>
                                    @endif
                                    <p class="text-sm text-gray-500">
                                        {{ __('Base price') }}: GH₵{{ number_format($item->base_price / 100, 2) }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-3">
                                    @if ($canToggleAvailability)
                                        <form method="POST" action="{{ route('dashboard.menu-items.toggle-availability', $item) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="text-xs px-2 py-1 rounded-md {{ ($pivot?->is_available ?? true) ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}"
                                            >{{ ($pivot?->is_available ?? true) ? __('Available') : __('Sold out') }}</button>
                                        </form>
                                    @endif

                                    @can('menu.edit_content')
                                        <a href="{{ route('dashboard.menu-items.edit', $item) }}" class="text-sm text-gray-600 hover:underline">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-sm text-gray-500">{{ __('No menu items yet.') }}</p>
        @endforelse
    </div>
</x-app-layout>
