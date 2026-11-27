{{-- A new purchase order, or a delivery recorded without one (R4). --}}
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $receiveNow ? __('Receive stock') : __('New purchase order') }}</h2></x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <form method="POST" action="{{ route('dashboard.inventory.purchase-orders.store') }}" class="bg-white shadow rounded-lg p-6 space-y-5">
            @csrf
            <input type="hidden" name="receive_now" value="{{ $receiveNow ? 1 : 0 }}">
            <p class="text-sm text-gray-600">{{ $receiveNow ? __('For a delivery that came without an order: what arrived and what it cost. Stock goes up as you save.') : __('What you\'re ordering. It\'s saved as a draft; mark it ordered when sent, and receive it when it arrives.') }}</p>
            <div>
                <x-input-label for="supplier_id" :value="__('Supplier (optional)')" />
                <select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id') === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <a href="{{ route('dashboard.inventory.suppliers.create') }}" class="text-xs text-indigo-600 hover:underline">{{ __('Add a supplier') }}</a>
            </div>
            @include('dashboard.inventory.partials.product-lines', ['mode' => 'buy', 'receiving' => $receiveNow])
            <p class="text-xs text-gray-500">{{ __('A pack\'s cost is for the whole pack. When it\'s received, the product\'s cost price becomes this latest cost.') }}</p>
            <div>
                <x-input-label for="note" :value="__('Note (optional)')" />
                <textarea id="note" name="note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('note') }}</textarea>
            </div>
            <div class="flex items-center gap-4">
                <x-primary-button>{{ $receiveNow ? __('Save: stock received') : __('Save draft') }}</x-primary-button>
                <a href="{{ route('dashboard.inventory.purchase-orders.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
