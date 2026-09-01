@php
    $branch = $branch ?? null;
    $value = fn (string $field, $default = null) => old($field, $branch?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="name" :value="__('Name')" required />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

@isset($branch)
    <div>
        <x-input-label for="slug" :value="__('Slug')" required />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="$value('slug')" required />
        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
    </div>
@endisset

<div>
    <x-input-label for="phone" :value="__('Phone')" required />
    <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="$value('phone')" required />
    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
</div>

<div>
    <x-input-label for="address" :value="__('Address')" required />
    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="$value('address')" required />
    <x-input-error class="mt-2" :messages="$errors->get('address')" />
</div>

<div>
    <x-input-label for="ghanapost_code" :value="__('GhanaPost GPS code (optional)')" />
    <x-text-input id="ghanapost_code" name="ghanapost_code" type="text" class="mt-1 block w-full" :value="$value('ghanapost_code')" placeholder="{{ __('e.g. GA-123-4567') }}" />
    <x-input-error class="mt-2" :messages="$errors->get('ghanapost_code')" />
</div>

@php
    $pickerLat = old('lat', $branch?->lat);
    $pickerLng = old('lng', $branch?->lng);
@endphp

<div x-data="branchLocationPicker(@js($pickerLat), @js($pickerLng))">
    <x-input-label :value="__('Location')" />
    <p class="text-sm text-gray-500 mb-2">{{ __('Search an address, drop the pin, drag it into place, or use your current location. The coordinates below update automatically. You can also type them in directly.') }}</p>

    <div class="flex flex-wrap gap-2 mb-2">
        <input
            type="text" x-model="searchQuery" @keydown.enter.prevent="search()"
            placeholder="{{ __('Search an address in Ghana…') }}"
            class="flex-1 min-w-[12rem] rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
        >
        <x-secondary-button @click="search()" x-bind:disabled="searching">
            <span x-show="!searching">{{ __('Search') }}</span>
            <span x-show="searching" x-cloak>{{ __('Searching…') }}</span>
        </x-secondary-button>
        <x-secondary-button @click="useMyLocation()" x-bind:disabled="locating">
            <span x-show="!locating">{{ __('Use my current location') }}</span>
            <span x-show="locating" x-cloak>{{ __('Locating…') }}</span>
        </x-secondary-button>
    </div>

    <p class="text-sm text-brand-red mb-2" x-show="error" x-cloak x-text="error"></p>

    <div x-ref="map" x-init="init()" class="w-full h-64 rounded-md border border-gray-300"></div>

    <div class="grid grid-cols-2 gap-4 mt-4">
        <div>
            <x-input-label for="lat" :value="__('Latitude')" required />
            <x-text-input
                id="lat" name="lat" type="text" class="mt-1 block w-full"
                x-model="lat" @change="recenterFromInputs()" required
            />
            <x-input-error class="mt-2" :messages="$errors->get('lat')" />
        </div>
        <div>
            <x-input-label for="lng" :value="__('Longitude')" required />
            <x-text-input
                id="lng" name="lng" type="text" class="mt-1 block w-full"
                x-model="lng" @change="recenterFromInputs()" required
            />
            <x-input-error class="mt-2" :messages="$errors->get('lng')" />
        </div>
    </div>
</div>

@include('dashboard.branches.partials.location-picker-script')

<div class="grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="opens_at" :value="__('Opens at')" required />
        <x-text-input id="opens_at" name="opens_at" type="time" class="mt-1 block w-full" :value="$value('opens_at')" required />
        <x-input-error class="mt-2" :messages="$errors->get('opens_at')" />
    </div>
    <div>
        <x-input-label for="closes_at" :value="__('Closes at')" required />
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
