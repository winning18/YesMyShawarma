{{-- Stock → Transfers between branches (R4). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-3">
            <div><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Transfers') }}</h2><p class="text-sm text-gray-500">{{ $branch->name }}</p></div>
            <a href="{{ route('dashboard.inventory.transfers.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Send stock') }}</a>
        </div>
    </x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
            @forelse ($transfers as $t)
                <a href="{{ route('dashboard.inventory.transfers.show', $t) }}" class="block px-4 py-3 hover:bg-gray-50 text-sm">
                    <span class="font-semibold">{{ $t->fromBranch->name }} → {{ $t->toBranch->name }}</span>
                    · {{ ['sent' => $t->to_branch_id === $branch->id ? __('On its way here: confirm when it arrives') : __('On its way'), 'received' => __('Received'), 'cancelled' => __('Cancelled')][$t->status] ?? $t->status }}
                    <span class="text-gray-500">· {{ trans_choice('{1} 1 product|[2,*] :count products', $t->items_count) }} · {{ $t->sent_at?->timezone('Africa/Accra')->format('j M Y') }}</span>
                </a>
            @empty
                <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No transfers yet.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
