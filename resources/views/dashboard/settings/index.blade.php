<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Settings') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.settings.update') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <h3 class="font-semibold text-gray-800">{{ __('Order reference numbers') }}</h3>
                <p class="text-sm text-gray-500">
                    {{ __('The prefix shown on every order reference, e.g. :example. A random 6-character code is added automatically.', ['example' => $posPrefix.'-4F8K2Q']) }}
                </p>
            </div>

            <div>
                <x-input-label for="order_reference_prefix_pos" :value="__('POS orders prefix')" required />
                <x-text-input
                    id="order_reference_prefix_pos" name="order_reference_prefix_pos" type="text"
                    class="mt-1 block w-full uppercase" :value="old('order_reference_prefix_pos', $posPrefix)" required
                    pattern="[A-Za-z0-9-]+" maxlength="20"
                />
                <x-input-error class="mt-2" :messages="$errors->get('order_reference_prefix_pos')" />
            </div>

            @module('website')
            <div>
                <x-input-label for="order_reference_prefix_web" :value="__('Web orders prefix')" required />
                <x-text-input
                    id="order_reference_prefix_web" name="order_reference_prefix_web" type="text"
                    class="mt-1 block w-full uppercase" :value="old('order_reference_prefix_web', $webPrefix)" required
                    pattern="[A-Za-z0-9-]+" maxlength="20"
                />
                <x-input-error class="mt-2" :messages="$errors->get('order_reference_prefix_web')" />
            </div>
            @endmodule

            @module('bolt_food')
            <div>
                <x-input-label for="order_reference_prefix_bolt_food" :value="__('Bolt Food orders prefix')" required />
                <x-text-input
                    id="order_reference_prefix_bolt_food" name="order_reference_prefix_bolt_food" type="text"
                    class="mt-1 block w-full uppercase" :value="old('order_reference_prefix_bolt_food', $boltFoodPrefix)" required
                    pattern="[A-Za-z0-9-]+" maxlength="20"
                />
                <p class="text-xs text-gray-400 mt-1">{{ __('For orders taken at the POS with payment method set to Bolt Food, so they\'re distinguishable from real in-house sales at a glance.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('order_reference_prefix_bolt_food')" />
            </div>
            @endmodule

            @module('whatsapp_reward')
            <div>
                <x-input-label for="order_reference_prefix_whatsapp" :value="__('WhatsApp group orders prefix')" required />
                <x-text-input
                    id="order_reference_prefix_whatsapp" name="order_reference_prefix_whatsapp" type="text"
                    class="mt-1 block w-full uppercase" :value="old('order_reference_prefix_whatsapp', $whatsappPrefix)" required
                    pattern="[A-Za-z0-9-]+" maxlength="20"
                />
                <x-input-error class="mt-2" :messages="$errors->get('order_reference_prefix_whatsapp')" />
            </div>
            @endmodule

            @module('freebies')
            <div>
                <x-input-label for="order_reference_prefix_freebie" :value="__('Freebies prefix')" required />
                <x-text-input
                    id="order_reference_prefix_freebie" name="order_reference_prefix_freebie" type="text"
                    class="mt-1 block w-full uppercase" :value="old('order_reference_prefix_freebie', $freebiePrefix)" required
                    pattern="[A-Za-z0-9-]+" maxlength="20"
                />
                <x-input-error class="mt-2" :messages="$errors->get('order_reference_prefix_freebie')" />
            </div>
            @endmodule

            <p class="text-xs text-gray-400">
                {{ __('Letters, numbers and dashes only. Only applies to new orders. Existing references are never changed.') }}
            </p>

            @module('whatsapp_reward')
            <div class="border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('WhatsApp group reward') }}</h3>
                <p class="text-sm text-gray-500 mb-3">{{ __('Taken off the food (never drinks) of every WhatsApp group order at the POS, pickup only. On a promotion day, an order that earns the promotion gets that instead.') }}</p>
                <x-input-label for="whatsapp_reward" :value="__('Amount (GH₵)')" required />
                <x-text-input id="whatsapp_reward" name="whatsapp_reward" type="number" min="0" step="0.01" class="mt-1 block w-40" :value="old('whatsapp_reward', $whatsappReward)" required />
                <x-input-error class="mt-2" :messages="$errors->get('whatsapp_reward')" />
            </div>
            @endmodule

            @module('tables')
            @can('business.manage')
            <div class="border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('How the POS works') }}</h3>
                <p class="text-sm text-gray-500 mb-3">{{ __('Eat-in tables, quick counter sales, or both.') }}</p>
                @php $mode = old('pos_mode', $posMode); @endphp
                @foreach ([
                    'both' => [__('Tables and quick sale'), __('The POS opens on the tables, with Quick sale for takeaway a tap away.')],
                    'tables' => [__('Tables only'), __('Every order belongs to a table. (Bolt Food orders and freebies need Quick sale.)')],
                    'quick' => [__('Quick sale only'), __('The menu and a single order, no tables.')],
                ] as $value => [$label, $hint])
                    <label class="flex items-start gap-3 mb-2">
                        <input type="radio" name="pos_mode" value="{{ $value }}" @checked($mode === $value) class="mt-1">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">{{ $label }}</span>
                            <span class="block text-xs text-gray-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
                <x-input-error class="mt-2" :messages="$errors->get('pos_mode')" />
            </div>
            @endcan
            @endmodule

            @module('delivery')
            <div class="border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('Delivery fees') }}</h3>
                <p class="text-sm text-gray-500 mb-3">{{ __('The fee is the straight-line distance from the branch times the rate per km, rounded to whole cedis and never below the minimum fee.') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach (['delivery_rate_per_km' => __('Per km (GH₵)'), 'delivery_minimum_fee' => __('Minimum fee (GH₵)'), 'delivery_minimum_order' => __('Smallest order for delivery (GH₵)')] as $field => $label)
                        <div>
                            <x-input-label :for="$field" :value="$label" required />
                            <x-text-input :id="$field" :name="$field" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old($field, $deliveryFees[$field])" required />
                            <x-input-error class="mt-2" :messages="$errors->get($field)" />
                        </div>
                    @endforeach
                </div>

                @can('business.manage')
                    <fieldset class="mt-5">
                        <legend class="text-sm font-semibold text-gray-800">{{ __('Who gets the delivery fee?') }}</legend>
                        <p class="text-xs text-gray-500 mb-2">{{ __('Customers always pay it. Riders always hand in the food money they collect, at a rider cash-up.') }}</p>
                        @php $handling = old('delivery_fee_handling', $deliveryFeeHandling); @endphp
                        <label class="flex items-start gap-3 mb-2">
                            <input type="radio" name="delivery_fee_handling" value="handed_in" @checked($handling === 'handed_in') class="mt-1">
                            <span>
                                <span class="block text-sm font-medium text-gray-800">{{ __('The business: riders hand fees in') }}</span>
                                <span class="block text-xs text-gray-500">{{ __('Fees are handed in with the food money and count as the business\'s income ("Delivery fees received" in Sales and Profit).') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="radio" name="delivery_fee_handling" value="kept_by_rider" @checked($handling === 'kept_by_rider') class="mt-1">
                            <span>
                                <span class="block text-sm font-medium text-gray-800">{{ __('The rider: they keep the fees') }}</span>
                                <span class="block text-xs text-gray-500">{{ __('For riders you don\'t employ. Fees stay out of every financial figure. A fee the customer paid to the business by Paystack or MoMo is the rider\'s, and comes off their cash-up.') }}</span>
                            </span>
                        </label>
                        <x-input-error class="mt-2" :messages="$errors->get('delivery_fee_handling')" />
                    </fieldset>
                @endcan
            </div>
            @endmodule

            <x-primary-button>{{ __('Save settings') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>
