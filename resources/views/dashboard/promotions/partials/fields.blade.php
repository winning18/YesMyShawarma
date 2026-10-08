@php
    $promotion = $promotion ?? null;
    $value = fn (string $field, $default = null) => old($field, $promotion?->{$field} ?? $default);
    $money = fn (string $field) => old($field, $promotion?->{$field} !== null ? $promotion->{$field} / 100 : null);
    $selectedBranchIds = old('branch_ids', $promotion?->branches->pluck('id')->all() ?? []);
    $selectedDays = old('recurring_days', $promotion?->recurring_days ?? []);
    $weekdays = [0 => __('Sun'), 1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat')];
@endphp

<div x-data="{ type: @js($value('type', 'percentage')), isAutomatic: @js((bool) $value('is_automatic', false)) }" class="space-y-6">
    <div>
        <x-input-label for="code" :value="__('Code')" required />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full uppercase" :value="$value('code')" required placeholder="{{ __('e.g. WELCOME10') }}" />
        <p class="text-xs text-gray-500 mt-1" x-show="isAutomatic">{{ __('Never shown to customers for an automatic promotion — just an internal label.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('code')" />
    </div>

    <div>
        <x-input-label for="type" :value="__('Type')" required />
        <select id="type" name="type" x-model="type" class="mt-1 block w-full rounded-md border-gray-300" required>
            <option value="percentage" @selected($value('type', 'percentage') === 'percentage')>{{ __('Percentage off') }}</option>
            <option value="fixed" @selected($value('type') === 'fixed')>{{ __('Fixed amount off') }}</option>
            <option value="buy_x_get_y_free" @selected($value('type') === 'buy_x_get_y_free')>{{ __('Buy X get Y free') }}</option>
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('type')" />
    </div>

    <div x-show="type !== 'buy_x_get_y_free'" x-cloak>
        <x-input-label for="value" :value="__('Value')" required />
        <div class="relative mt-1">
            <x-text-input
                id="value" name="value" type="number" min="0" class="block w-full"
                :value="$value('type') === 'fixed' ? $money('value') : $value('value')"
                x-bind:required="type !== 'buy_x_get_y_free'"
                x-bind:step="type === 'percentage' ? 1 : 0.01" x-bind:max="type === 'percentage' ? 100 : null"
            />
            <span class="absolute inset-y-0 right-3 flex items-center text-sm text-gray-400" x-text="type === 'percentage' ? '%' : 'GH₵'"></span>
        </div>
        <p class="text-xs text-gray-500 mt-1" x-show="type === 'percentage'">{{ __('A whole number between 1 and 100.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('value')" />
    </div>

    <div x-show="type === 'buy_x_get_y_free'" x-cloak class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="buy_quantity" :value="__('Buy quantity')" />
            <x-text-input
                id="buy_quantity" name="buy_quantity" type="number" min="2" class="mt-1 block w-full"
                :value="$value('buy_quantity', 3)" x-bind:required="type === 'buy_x_get_y_free'"
            />
            <p class="text-xs text-gray-500 mt-1">{{ __('Of the SAME menu item, e.g. 3.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('buy_quantity')" />
        </div>
        <div>
            <x-input-label for="free_quantity" :value="__('Free quantity')" />
            <x-text-input
                id="free_quantity" name="free_quantity" type="number" min="1" class="mt-1 block w-full"
                :value="$value('free_quantity', 1)" x-bind:required="type === 'buy_x_get_y_free'"
            />
            <p class="text-xs text-gray-500 mt-1">{{ __('Repeats per group, e.g. 1 free per 3 bought.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('free_quantity')" />
        </div>
    </div>

    <div>
        <x-input-label for="min_order_total" :value="__('Minimum order total (optional, GH₵)')" />
        <x-text-input id="min_order_total" name="min_order_total" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$money('min_order_total')" />
        <x-input-error class="mt-2" :messages="$errors->get('min_order_total')" />
    </div>

    <div class="border-t border-gray-100 pt-4">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_automatic" value="1" x-model="isAutomatic" class="rounded border-gray-300">
            {{ __('Applies automatically, no code needed') }}
        </label>
        <p class="text-xs text-gray-500 mt-1">{{ __("Hides the discount-code field at checkout/POS on any day this applies — it always takes the order's one promotion slot over a customer-entered code.") }}</p>
    </div>

    <div x-show="isAutomatic" x-cloak class="space-y-4">
        <div>
            <x-input-label :value="__('Repeats on')" />
            <div class="mt-2 flex gap-3">
                @foreach ($weekdays as $day => $label)
                    <label class="flex items-center gap-1 text-sm">
                        <input type="checkbox" name="recurring_days[]" value="{{ $day }}" @checked(in_array($day, $selectedDays)) class="rounded border-gray-300">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('recurring_days')" />
        </div>

        <div>
            <x-input-label for="banner_headline" :value="__('Homepage banner headline (optional)')" />
            <x-text-input id="banner_headline" name="banner_headline" type="text" class="mt-1 block w-full" :value="$value('banner_headline')" placeholder="{{ __('e.g. Buy 2 Get 1 Free — every Wednesday!') }}" />
            <x-input-error class="mt-2" :messages="$errors->get('banner_headline')" />
        </div>

        @unless ($promotion)
            <p class="text-xs text-gray-500">{{ __('Save this promotion first to upload a banner image.') }}</p>
        @endunless
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
