@php
    $promotion = $promotion ?? null;
    $value = fn (string $field, $default = null) => old($field, $promotion?->{$field} ?? $default);
    $money = fn (string $field) => old($field, $promotion?->{$field} !== null ? $promotion->{$field} / 100 : null);
    $selectedBranchIds = old('branch_ids', $promotion?->branches->pluck('id')->all() ?? []);
@endphp

<div x-data="{ type: @js($value('type', 'percentage')) }" class="space-y-6">
    <div>
        <x-input-label for="code" :value="__('Code')" required />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full uppercase" :value="$value('code')" required placeholder="{{ __('e.g. WELCOME10') }}" />
        <x-input-error class="mt-2" :messages="$errors->get('code')" />
    </div>

    <div>
        <x-input-label for="type" :value="__('Type')" required />
        <select id="type" name="type" x-model="type" class="mt-1 block w-full rounded-md border-gray-300" required>
            <option value="percentage" @selected($value('type', 'percentage') === 'percentage')>{{ __('Percentage off') }}</option>
            <option value="fixed" @selected($value('type') === 'fixed')>{{ __('Fixed amount off') }}</option>
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('type')" />
    </div>

    <div>
        <x-input-label for="value" :value="__('Value')" required />
        <div class="relative mt-1">
            <x-text-input
                id="value" name="value" type="number" min="0" class="block w-full"
                :value="$value('type') === 'fixed' ? $money('value') : $value('value')" required
                x-bind:step="type === 'percentage' ? 1 : 0.01" x-bind:max="type === 'percentage' ? 100 : null"
            />
            <span class="absolute inset-y-0 right-3 flex items-center text-sm text-gray-400" x-text="type === 'percentage' ? '%' : 'GH₵'"></span>
        </div>
        <p class="text-xs text-gray-500 mt-1" x-show="type === 'percentage'">{{ __('A whole number between 1 and 100.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('value')" />
    </div>

    <div>
        <x-input-label for="min_order_total" :value="__('Minimum order total (optional, GH₵)')" />
        <x-text-input id="min_order_total" name="min_order_total" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$money('min_order_total')" />
        <x-input-error class="mt-2" :messages="$errors->get('min_order_total')" />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="starts_at" :value="__('Starts (optional)')" />
            <x-text-input id="starts_at" name="starts_at" type="datetime-local" class="mt-1 block w-full" :value="$promotion?->starts_at?->format('Y-m-d\TH:i')" />
            <x-input-error class="mt-2" :messages="$errors->get('starts_at')" />
        </div>
        <div>
            <x-input-label for="ends_at" :value="__('Ends (optional)')" />
            <x-text-input id="ends_at" name="ends_at" type="datetime-local" class="mt-1 block w-full" :value="$promotion?->ends_at?->format('Y-m-d\TH:i')" />
            <x-input-error class="mt-2" :messages="$errors->get('ends_at')" />
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="max_redemptions" :value="__('Max total uses (optional)')" />
            <x-text-input id="max_redemptions" name="max_redemptions" type="number" min="1" class="mt-1 block w-full" :value="$value('max_redemptions')" />
            <x-input-error class="mt-2" :messages="$errors->get('max_redemptions')" />
        </div>
        <div>
            <x-input-label for="max_per_customer" :value="__('Max uses per customer (optional)')" />
            <x-text-input id="max_per_customer" name="max_per_customer" type="number" min="1" class="mt-1 block w-full" :value="$value('max_per_customer')" />
            <x-input-error class="mt-2" :messages="$errors->get('max_per_customer')" />
        </div>
    </div>

    <div>
        <x-input-label :value="__('Branches (leave all unchecked for every branch)')" />
        <div class="mt-2 space-y-1">
            @foreach ($branches as $branch)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $selectedBranchIds)) class="rounded border-gray-300">
                    {{ $branch->name }}
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('branch_ids')" />
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promotion?->is_active ?? true)) class="rounded border-gray-300">
        {{ __('Active') }}
    </label>
</div>
