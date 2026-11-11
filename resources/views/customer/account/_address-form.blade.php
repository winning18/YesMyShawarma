{{-- Add / edit a saved address. $address is null when adding. --}}
@php
    $areaValue = $address ? ($address->delivery_area_id ?? ($address->area_other ? 'other' : '')) : old('area_id', '');
@endphp
<form method="POST" action="{{ $action }}" class="space-y-3"
    x-data="{
        area: @js((string) $areaValue),
        lat: null, lng: null, locating: false, locationNote: '',
        locate() {
            if (! navigator.geolocation) { this.locationNote = @js(__('Your phone can\'t share its location here.')); return; }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (position) => { this.lat = position.coords.latitude; this.lng = position.coords.longitude; this.locating = false; this.locationNote = @js(__('Location captured.')); },
                () => { this.locating = false; this.locationNote = @js(__('We couldn\'t get your location. You can still save the address and share it at checkout.')); },
                { timeout: 15000 },
            );
        },
    }">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div>
        <label class="block text-sm font-medium mb-1">{{ __('Name it (optional)') }}</label>
        <input type="text" name="label" maxlength="50" value="{{ $address->label ?? old('label') }}" placeholder="{{ __('e.g. Home, Work') }}" class="w-full rounded-md border-brand-gray-300">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">{{ __('Area') }} <span class="text-brand-red">*</span></label>
        <select name="area_id" required x-model="area" class="w-full rounded-md border-brand-gray-300">
            <option value="">{{ __('Select your area…') }}</option>
            @foreach ($deliveryAreas as $area)
                <option value="{{ $area->id }}">{{ $area->name }}</option>
            @endforeach
            <option value="other">{{ __('Other (not listed)') }}</option>
        </select>
        <input x-show="area === 'other'" x-cloak type="text" name="area_other" maxlength="100" :required="area === 'other'"
            value="{{ $address->area_other ?? old('area_other') }}" placeholder="{{ __('Type your area name') }}" class="mt-2 w-full rounded-md border-brand-gray-300">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">{{ __('Landmark') }} <span class="text-brand-red">*</span></label>
        <input type="text" name="landmark" required maxlength="255" value="{{ $address->landmark ?? old('landmark') }}" placeholder="{{ __('e.g. Opposite the blue gate, near the church') }}" class="w-full rounded-md border-brand-gray-300">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">{{ __('GhanaPost GPS code (optional)') }}</label>
        <input type="text" name="ghanapost_code" maxlength="20" value="{{ $address->ghanapost_code ?? old('ghanapost_code') }}" placeholder="{{ __('e.g. GA-123-4567') }}" class="w-full rounded-md border-brand-gray-300">
    </div>

    <div>
        <input type="hidden" name="lat" :value="lat">
        <input type="hidden" name="lng" :value="lng">
        <button type="button" @click="locate()" :disabled="locating" class="px-4 py-2 bg-brand-red text-brand-white text-sm font-semibold rounded-md hover:bg-brand-red-dark disabled:opacity-60">
            <span x-text="locating ? @js(__('Locating…')) : @js($address && $address->lat !== null ? __('Update to my current location') : __('Use my current location'))"></span>
        </button>
        <p x-show="locationNote" x-text="locationNote" class="text-xs text-brand-gray-500 mt-1"></p>
    </div>

    <button type="submit" class="px-6 py-2.5 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">{{ $submitLabel }}</button>
</form>
