@php
    $id = $pack ? 'pack'.$pack->id : 'pack-new';
    // Typed values come back after an error only on the form that was sent.
    $old = fn (string $field, $value) => ($pack === null && old('pack_name') !== null) ? old($field) : $value;
@endphp
<div class="col-span-2 sm:col-span-1">
    <label for="{{ $id }}-name" class="block text-xs font-medium text-gray-500">{{ __('Pack name') }} <span class="text-red-600">*</span></label>
    <input id="{{ $id }}-name" name="pack_name" value="{{ $old('pack_name', $pack?->name) }}" maxlength="60" required placeholder="{{ __('e.g. Strip of 10') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
</div>
<div>
    <label for="{{ $id }}-units" class="block text-xs font-medium text-gray-500">{{ __('Units in it') }} <span class="text-red-600">*</span></label>
    <input id="{{ $id }}-units" name="pack_units" type="number" min="1" value="{{ $old('pack_units', $pack?->units) }}" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
</div>
<div>
    <label for="{{ $id }}-price" class="block text-xs font-medium text-gray-500">{{ __('Price (GH₵)') }} <span class="text-red-600">*</span></label>
    <input id="{{ $id }}-price" name="pack_price" type="number" step="0.01" min="0.01" value="{{ $old('pack_price', $pack ? $cedis($pack->price) : '') }}" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
</div>
<div>
    <label for="{{ $id }}-cost" class="block text-xs font-medium text-gray-500">{{ __('Cost (GH₵)') }}</label>
    <input id="{{ $id }}-cost" name="pack_cost_price" type="number" step="0.01" min="0" value="{{ $old('pack_cost_price', $pack ? $cedis($pack->cost_price) : '') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
</div>
<div>
    <label for="{{ $id }}-barcode" class="block text-xs font-medium text-gray-500">{{ __('Barcode') }}</label>
    <input id="{{ $id }}-barcode" name="pack_barcode" value="{{ $old('pack_barcode', $pack?->barcode) }}" maxlength="64" inputmode="numeric" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono">
</div>
