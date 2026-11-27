{{-- Stock → Stock counts (R4). --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Stock counts') }}</h2><p class="text-sm text-gray-500">{{ $branch->name }}</p></x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <form method="POST" action="{{ route('dashboard.inventory.counts.store') }}" class="bg-white shadow rounded-lg p-6 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <x-input-label for="category_id" :value="__('What to count')" />
                <select id="category_id" name="category_id" class="mt-1 rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('Every product') }}</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[12rem]">
                <x-input-label for="note" :value="__('Note (optional)')" />
                <x-text-input id="note" name="note" class="mt-1 block w-full" placeholder="{{ __('e.g. End of month') }}" />
            </div>
            <x-primary-button>{{ __('Start a count') }}</x-primary-button>
        </form>
        <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
            @forelse ($counts as $count)
                <a href="{{ route('dashboard.inventory.counts.show', $count) }}" class="block px-4 py-3 hover:bg-gray-50 text-sm">
                    <span class="font-semibold">{{ $count->created_at->timezone('Africa/Accra')->format('j M Y, H:i') }}</span>
                    · {{ ['open' => __('Open'), 'finished' => __('Finished'), 'cancelled' => __('Cancelled')][$count->status] ?? $count->status }}
                    · {{ trans_choice('{1} 1 product|[2,*] :count products', $count->items_count) }}
                    <span class="text-gray-500">· {{ $count->starter?->name }}{{ $count->note ? ' · '.$count->note : '' }}</span>
                </a>
            @empty
                <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No counts yet.') }}</p>
            @endforelse
        </div>
        {{ $counts->links() }}
    </div>
</x-app-layout>
