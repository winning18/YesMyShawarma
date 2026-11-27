{{--
    Pack sizes (R2, 2026-11-27; ProductPackController): a pharmacy's or
    shop's product sold by the strip, box or crate too, each with its own
    price and barcode. Not for a restaurant.
--}}
@unlessrestaurant
@php
    $cedis = fn (?int $pesewas) => $pesewas === null ? '' : number_format($pesewas / 100, 2, '.', '');
    $unit = $menuItem->unit_name ?: __('unit');
@endphp
<div id="packs" class="bg-white shadow rounded-lg p-6 space-y-4">
    <div>
        <h3 class="font-semibold text-gray-800">{{ __('Pack sizes') }}</h3>
        <p class="text-xs text-gray-500 mt-1">{{ __('If you also sell this by the strip, box or crate, add each pack with its own price and barcode. The price above is for one :unit.', ['unit' => $unit]) }}</p>
    </div>

    @foreach ($menuItem->packs as $pack)
        <div class="border-b border-gray-100 pb-3 last:border-0">
            <form method="POST" action="{{ route('dashboard.menu-items.packs.update', [$menuItem, $pack]) }}" class="grid grid-cols-2 sm:grid-cols-6 gap-2 items-end">
                @csrf
                @method('PUT')
                @include('dashboard.menu-items.partials.pack-fields', ['pack' => $pack])
                <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">{{ __('Save') }}</button>
            </form>
            <form method="POST" action="{{ route('dashboard.menu-items.packs.destroy', [$menuItem, $pack]) }}" class="mt-1" onsubmit="return confirm(@js(__('Remove :name?', ['name' => $pack->name])))">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Remove :name', ['name' => $pack->name]) }}</button>
            </form>
        </div>
    @endforeach

    <form method="POST" action="{{ route('dashboard.menu-items.packs.store', $menuItem) }}" class="grid grid-cols-2 sm:grid-cols-6 gap-2 items-end">
        @csrf
        @include('dashboard.menu-items.partials.pack-fields', ['pack' => null])
        <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900">{{ __('Add pack') }}</button>
    </form>

    @foreach (['pack_name', 'pack_units', 'pack_price', 'pack_cost_price', 'pack_barcode'] as $field)
        @error($field)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @endforeach
</div>
@endrestaurant
