@php
    $optionGroup = $optionGroup ?? null;
    $value = fn (string $field, $default = null) => old($field, $optionGroup?->{$field} ?? $default);
@endphp

<div>
    <x-input-label for="name" :value="__('Name')" required />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="min_select" :value="__('Min select')" required />
        <x-text-input id="min_select" name="min_select" type="number" min="0" class="mt-1 block w-full" :value="$value('min_select', 0)" required />
        <x-input-error class="mt-2" :messages="$errors->get('min_select')" />
    </div>
    <div>
        <x-input-label for="max_select" :value="__('Max select')" required />
        <x-text-input id="max_select" name="max_select" type="number" min="1" class="mt-1 block w-full" :value="$value('max_select', 1)" required />
        <x-input-error class="mt-2" :messages="$errors->get('max_select')" />
    </div>
</div>

<div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $optionGroup?->is_required ?? false)) class="rounded border-gray-300">
        {{ __('Required: the customer must choose before adding this item') }}
    </label>
</div>

<script>
    {{-- Mirrors the server's "max_select can't be less than min_select"
         closure — native HTML5 constraint validation blocks submission
         and shows the browser's own inline hint, no Alpine needed here. --}}
    (function () {
        const min = document.getElementById('min_select');
        const max = document.getElementById('max_select');
        if (!min || !max) return;

        const check = () => {
            max.setCustomValidity(
                Number(max.value) < Number(min.value) ? @js(__('Max select can\'t be less than min select.')) : ''
            );
        };

        min.addEventListener('input', check);
        max.addEventListener('input', check);
        check();
    })();
</script>
