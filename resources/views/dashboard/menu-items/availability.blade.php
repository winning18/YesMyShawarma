<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Item availability') }}</h2>
            <p class="text-sm text-gray-500">{{ $branch->name }}</p>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-8">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        @forelse ($categories as $group)
            <section class="space-y-3">
                <h3 class="font-semibold text-gray-800">{{ $group['category']->name }}</h3>

                <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
                    @foreach ($group['items'] as $row)
                        @php $item = $row['item']; $pivot = $row['pivot']; @endphp
                        <div class="p-4 flex items-center justify-between gap-4">
                            <p class="text-gray-800">{{ $item->name }}</p>

                            <form method="POST" action="{{ route('dashboard.menu-items.toggle-availability', $item) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="text-xs px-2 py-1 rounded-md {{ ($pivot?->is_available ?? true) ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}"
                                >{{ ($pivot?->is_available ?? true) ? __('Available') : __('Sold out') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-sm text-gray-500">{{ __('No menu items yet.') }}</p>
        @endforelse
    </div>
</x-app-layout>
