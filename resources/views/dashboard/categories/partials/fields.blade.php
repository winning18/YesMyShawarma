@php
    $category = $category ?? null;
    $value = fn (string $field, $default = null) => old($field, $category?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="tagline" :value="__('Tagline (optional)')" />
    <x-text-input id="tagline" name="tagline" type="text" class="mt-1 block w-full" :value="$value('tagline')" />
    <p class="mt-1 text-xs text-gray-500">{{ __('Shown on the home page hero slide, if this category has a hero photo.') }}</p>
    <x-input-error class="mt-2" :messages="$errors->get('tagline')" />
</div>

@isset($category)
    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="$value('slug')" required />
        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
    </div>
@endisset

<div>
    <x-input-label for="sort_order" :value="__('Sort order')" />
    <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="$value('sort_order', 0)" />
    <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
</div>

<div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true)) class="rounded border-gray-300">
        {{ __('Active') }}
    </label>
</div>
