@php
    $item = $item ?? null;
    $value = fn (string $field, $default = null) => old($field, $item?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="name" :value="__('Name')" required />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="unit" :value="__('Unit')" required />
    <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="$value('unit')" placeholder="{{ __('e.g. pieces, kg, packs, bottles') }}" required />
    <x-input-error class="mt-2" :messages="$errors->get('unit')" />
</div>

@unless ($item)
    <div>
        <x-input-label for="quantity" :value="__('Starting quantity')" required />
        <x-text-input id="quantity" name="quantity" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$value('quantity', 0)" required />
        <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
    </div>
@endunless

<div>
    <x-input-label for="low_stock_threshold" :value="__('Low-stock reminder limit')" required />
    <p class="text-xs text-gray-500 mb-1">{{ __("The owner gets an SMS the moment quantity drops below this number.") }}</p>
    <x-text-input id="low_stock_threshold" name="low_stock_threshold" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$value('low_stock_threshold')" required />
    <x-input-error class="mt-2" :messages="$errors->get('low_stock_threshold')" />
</div>
