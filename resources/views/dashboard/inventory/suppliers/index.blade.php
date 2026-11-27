{{-- Stock → Suppliers (R4). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Suppliers') }}</h2>
            <a href="{{ route('dashboard.inventory.suppliers.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Add supplier') }}</a>
        </div>
    </x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
            @forelse ($suppliers as $supplier)
                <a href="{{ route('dashboard.inventory.suppliers.edit', $supplier) }}" class="block px-4 py-3 hover:bg-gray-50">
                    <span class="font-semibold text-gray-800">{{ $supplier->name }}</span>
                    <span class="block text-xs text-gray-500">{{ collect([$supplier->phone, $supplier->email])->filter()->join(' · ') }} · {{ trans_choice('{0} no orders yet|{1} 1 order|[2,*] :count orders', $supplier->purchase_orders_count) }}</span>
                </a>
            @empty
                <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No suppliers yet. Add the companies you buy stock from.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
