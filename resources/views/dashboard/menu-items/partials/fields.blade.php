@php
    $menuItem = $menuItem ?? null;
    $assignedOptionGroupIds = $assignedOptionGroupIds ?? [];
    $value = fn (string $field, $default = null) => old($field, $menuItem?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="category_id" :value="__('Category')" />
    <select id="category_id" name="category_id" class="mt-1 block w-full rounded-md border-gray-300" required>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $menuItem?->category_id) == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <x-input-error class="mt-2" :messages="$errors->get('category_id')" />
</div>

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

@isset($menuItem)
    <div>
        <x-input-label for="slug" :value="__('Slug')" />
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
    <x-input-label for="base_price" :value="__('Base price (GH₵)')" />
    <x-text-input
        id="base_price" name="base_price" type="number" step="0.01" min="0.01" class="mt-1 block w-full"
        :value="old('base_price', $menuItem ? number_format($menuItem->base_price / 100, 2, '.', '') : '')" required
    />
    <x-input-error class="mt-2" :messages="$errors->get('base_price')" />
</div>

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

@if ($optionGroups->isNotEmpty())
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
