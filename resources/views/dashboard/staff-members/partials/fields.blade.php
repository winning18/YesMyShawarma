@php
    $staffMember = $staffMember ?? null;
    $value = fn (string $field, $default = null) => old($field, $staffMember?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="name" :value="__('Name')" required />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="title" :value="__('Title (optional)')" />
    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="$value('title')" placeholder="{{ __('e.g. Branch Manager') }}" />
    <x-input-error class="mt-2" :messages="$errors->get('title')" />
</div>

<div>
    <x-input-label for="sort_order" :value="__('Sort order')" />
    <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="$value('sort_order', 0)" />
    <p class="mt-1 text-xs text-gray-500">{{ __('Lower numbers appear first on the About page.') }}</p>
    <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
</div>

<div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staffMember?->is_active ?? true)) class="rounded border-gray-300">
        {{ __('Active: shown on the About page') }}
    </label>
</div>
