@props([
    'areas',
    'model',
    'name' => null,
    'placeholder' => null,
    'required' => 'false',
    'inputClass' => 'w-full rounded-md border-gray-300 text-sm',
])
{{--
    Delivery area picker with a search box (2026-11-20), for the POS and
    the website checkout. Type to filter the areas, tap one (or Enter for
    the first match); "Other (not listed)" is always offered. Bound to the
    page's own variable through x-model ($model), with the same values the
    old <select> gave: an area id as a string, 'other', or ''. $name adds a
    hidden form field for a normal form post; $required is a JS expression.
--}}
@php
    $list = collect($areas)->map(fn ($area) => ['id' => (string) $area->id, 'name' => $area->name])->values();
@endphp
<div
    x-data="{
        value: '',
        query: '',
        open: false,
        areas: @js($list),
        otherLabel: @js(__('Other (not listed)')),
        get label() {
            if (this.value === 'other') return this.otherLabel;
            return this.areas.find((area) => area.id === String(this.value))?.name ?? '';
        },
        matches() {
            const q = this.query.trim().toLowerCase();
            return q === '' ? this.areas : this.areas.filter((area) => area.name.toLowerCase().includes(q));
        },
        pick(id) {
            this.value = id;
            this.query = '';
            this.open = false;
        },
        pickFirst() {
            const first = this.matches()[0];
            this.pick(first ? first.id : 'other');
        },
    }"
    x-modelable="value"
    x-model="{{ $model }}"
    @click.outside="open = false"
    class="relative"
>
    <input
        type="text"
        autocomplete="off"
        role="combobox"
        :aria-expanded="open"
        :value="open ? query : label"
        @focus="open = true; query = ''"
        @click="open = true"
        @input="query = $event.target.value; open = true"
        @keydown.escape="open = false"
        @keydown.enter.prevent="pickFirst()"
        @keydown.tab="open = false"
        :required="({{ $required }}) && ! value"
        placeholder="{{ $placeholder ?? __('Search or choose your area…') }}"
        {{ $attributes->merge(['class' => $inputClass]) }}
    >
    @if ($name)
        <input type="hidden" name="{{ $name }}" :value="value">
    @endif

    <ul x-show="open" x-cloak class="absolute z-30 mt-1 w-full max-h-60 overflow-y-auto rounded-md border border-gray-200 bg-white text-sm shadow-lg" role="listbox">
        <template x-for="area in matches()" :key="area.id">
            <li>
                <button type="button" @click="pick(area.id)" class="w-full px-3 py-2 text-left hover:bg-gray-100" :class="String(value) === area.id ? 'font-semibold' : ''" x-text="area.name"></button>
            </li>
        </template>
        <li x-show="matches().length === 0" class="px-3 py-2 text-gray-500">{{ __('No area matches. Choose "Other" and type it in.') }}</li>
        <li class="border-t border-gray-100">
            <button type="button" @click="pick('other')" class="w-full px-3 py-2 text-left text-gray-600 hover:bg-gray-100">{{ __('Other (not listed)') }}</button>
        </li>
    </ul>
</div>
