@php
    $menuItem = $menuItem ?? null;
    $assignedOptionGroupIds = $assignedOptionGroupIds ?? [];
    $value = fn (string $field, $default = null) => old($field, $menuItem?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="category_id" :value="__('Category')" required />
    <select id="category_id" name="category_id" class="mt-1 block w-full rounded-md border-gray-300" required>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $menuItem?->category_id) == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
</div>

<div>
    <x-input-label for="name" :value="__('Name')" required />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

@isset($menuItem)
    <div>
        <x-input-label for="slug" :value="__('Slug')" required />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="$value('slug')" required />
        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
    </div>
@endisset

<div>
    <x-input-label for="description" :value="__('Description (optional)')" />
    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ $value('description') }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('description')" />
</div>

<div>
    <x-input-label for="base_price" :value="__('Base price (GH₵)')" required />
    <x-text-input
        id="base_price" name="base_price" type="number" step="0.01" min="0.01" class="mt-1 block w-full"
        :value="old('base_price', $menuItem ? number_format($menuItem->base_price / 100, 2, '.', '') : '')" required
    />
    <x-input-error class="mt-2" :messages="$errors->get('base_price')" />
</div>

{{-- A pharmacy's or shop's product fields (R2, ProductCatalogService). --}}
@unlessrestaurant
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="sku" :value="__('Product code (SKU, optional)')" />
            <x-text-input id="sku" name="sku" type="text" maxlength="64" class="mt-1 block w-full font-mono" :value="$value('sku')" />
            <p class="text-xs text-gray-500 mt-1">{{ __('Leave blank and one is made, e.g. P-00042.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('sku')" />
        </div>
        <div>
            <x-input-label for="barcode" :value="__('Barcode (optional)')" />
            <x-text-input id="barcode" name="barcode" type="text" maxlength="64" inputmode="numeric" class="mt-1 block w-full font-mono" :value="$value('barcode')" />
            <p class="text-xs text-gray-500 mt-1">{{ __('Click here and scan the item, or type the number under the bars.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('barcode')" />
        </div>
        <div>
            <x-input-label for="unit_name" :value="__('Sold by (optional)')" />
            <x-text-input id="unit_name" name="unit_name" type="text" maxlength="30" class="mt-1 block w-full" :value="$value('unit_name')" placeholder="{{ __('e.g. tablet, piece, bottle') }}" />
            <x-input-error class="mt-2" :messages="$errors->get('unit_name')" />
        </div>
        <div>
            <x-input-label for="cost_price" :value="__('Cost price, per unit (GH₵, optional)')" />
            <x-text-input id="cost_price" name="cost_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                :value="old('cost_price', $menuItem && $menuItem->cost_price !== null ? number_format($menuItem->cost_price / 100, 2, '.', '') : '')" />
            <p class="text-xs text-gray-500 mt-1">{{ __('What you pay for it. Used for profit; customers never see it.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('cost_price')" />
        </div>
    </div>

    {{-- A pharmacy's medicine details (R5). --}}
    @pharmacy
        <div class="border-t border-gray-100 pt-4 space-y-4">
            <div>
                <x-input-label for="medicine_class" :value="__('Medicine class')" />
                <select id="medicine_class" name="medicine_class" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">{{ __('Not a medicine') }}</option>
                    @foreach (\App\Models\MenuItem::MEDICINE_CLASSES as $key => $label)
                        <option value="{{ $key }}" @selected(old('medicine_class', $menuItem?->medicine_class) === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">{{ __('A medicine is kept in batches with expiry dates. Pharmacy medicines need a manager\'s approval at the till; Prescription only and Controlled need a prescription too.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('medicine_class')" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach (['generic_name' => [__('Generic name'), __('e.g. Paracetamol')], 'strength' => [__('Strength'), __('e.g. 500mg, 125mg/5ml')], 'dosage_form' => [__('Form'), __('e.g. Tablet, Syrup, Capsule')], 'fda_number' => [__('FDA Ghana registration no.'), '']] as $field => [$label, $hint])
                    <div>
                        <x-input-label :for="$field" :value="$label" />
                        <x-text-input :id="$field" :name="$field" type="text" class="mt-1 block w-full" :value="$value($field)" placeholder="{{ $hint }}" />
                        <x-input-error class="mt-2" :messages="$errors->get($field)" />
                    </div>
                @endforeach
            </div>
        </div>
    @endpharmacy
@endrestaurant

<div>
    <x-input-label for="sort_order" :value="__('Sort order')" />
    <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="$value('sort_order', 0)" />
    <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
</div>

<div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menuItem?->is_active ?? true)) class="rounded border-gray-300">
        {{ __('Active') }}
    </label>
</div>

@if ($optionGroups->isNotEmpty() && app(\App\Services\Settings\BusinessType::class)->isRestaurant())
    <div>
        <x-input-label :value="__('Option groups')" />
        <div class="mt-1 space-y-1">
            @foreach ($optionGroups as $group)
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox" name="option_group_ids[]" value="{{ $group->id }}"
                        @checked(in_array($group->id, old('option_group_ids', $assignedOptionGroupIds)))
                        class="rounded border-gray-300"
                    >
                    {{ $group->name }}
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('option_group_ids')" />
    </div>
@endif
