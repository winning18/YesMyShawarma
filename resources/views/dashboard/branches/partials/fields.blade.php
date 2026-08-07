@php
    $branch = $branch ?? null;
    $value = fn (string $field, $default = null) => old($field, $branch?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

@isset($branch)
    <div>
        <x-input-label for="slug" :value="__('Slug')" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="$value('slug')" required />
        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
    </div>
@endisset

<div>
    <x-input-label for="phone" :value="__('Phone')" />
    <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="$value('phone')" required />
    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
</div>

<div>
    <x-input-label for="address" :value="__('Address')" />
    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="$value('address')" required />
    <x-input-error class="mt-2" :messages="$errors->get('address')" />
</div>

<div>
    <x-input-label for="ghanapost_code" :value="__('GhanaPost GPS code (optional)')" />
    <x-text-input id="ghanapost_code" name="ghanapost_code" type="text" class="mt-1 block w-full" :value="$value('ghanapost_code')" placeholder="{{ __('e.g. GA-123-4567') }}" />
    <x-input-error class="mt-2" :messages="$errors->get('ghanapost_code')" />
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="lat" :value="__('Latitude')" />
        <x-text-input id="lat" name="lat" type="text" class="mt-1 block w-full" :value="$value('lat')" required />
        <x-input-error class="mt-2" :messages="$errors->get('lat')" />
    </div>
    <div>
        <x-input-label for="lng" :value="__('Longitude')" />
        <x-text-input id="lng" name="lng" type="text" class="mt-1 block w-full" :value="$value('lng')" required />
        <x-input-error class="mt-2" :messages="$errors->get('lng')" />
    </div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="opens_at" :value="__('Opens at')" />
        <x-text-input id="opens_at" name="opens_at" type="time" class="mt-1 block w-full" :value="$value('opens_at')" required />
        <x-input-error class="mt-2" :messages="$errors->get('opens_at')" />
    </div>
    <div>
        <x-input-label for="closes_at" :value="__('Closes at')" />
        <x-text-input id="closes_at" name="closes_at" type="time" class="mt-1 block w-full" :value="$value('closes_at')" required />
        <x-input-error class="mt-2" :messages="$errors->get('closes_at')" />
    </div>
</div>

<div class="space-y-2">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_accepting_orders" value="1" @checked(old('is_accepting_orders', $branch?->is_accepting_orders ?? true)) class="rounded border-gray-300">
        {{ __('Accepting orders') }}
    </label>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch?->is_active ?? true)) class="rounded border-gray-300">
        {{ __('Active') }}
    </label>
</div>
