<x-customer-layout title="My account · {{ config('app.name') }}">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">{{ __('My account') }}</h1>
        @include('customer.account._nav', ['active' => 'details'])

        <div class="space-y-6">
            <section class="border border-brand-gray-100 rounded-lg p-5">
                <h2 class="font-bold mb-4">{{ __('Your details') }}</h2>
                <form method="POST" action="{{ route('customer.account.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="name" class="block text-sm font-medium mb-1">{{ __('Name') }} <span class="text-brand-red">*</span></label>
                        <input id="name" type="text" name="name" required maxlength="255" autocomplete="name" value="{{ old('name', $customer->name) }}"
                            class="w-full rounded-md {{ $errors->has('name') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}">
                        @error('name') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1">{{ __('Email (optional)') }}</label>
                        <input id="email" type="email" name="email" maxlength="255" autocomplete="email" value="{{ old('email', $customer->email) }}"
                            class="w-full rounded-md {{ $errors->has('email') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}">
                        @error('email') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Save details') }}</button>
                </form>
            </section>

            <section class="border border-brand-gray-100 rounded-lg p-5">
                <h2 class="font-bold mb-1">{{ __('Phone number') }}</h2>
                <p class="text-sm text-brand-gray-500 mb-4">
                    {{ __('You log in with :phone and order updates are texted there.', ['phone' => $customer->phone]) }}
                </p>

                @if ($errors->phone->any())
                    <div class="mb-4 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">{{ $errors->phone->first() }}</div>
                @endif

                @if ($pendingPhone)
                    <form method="POST" action="{{ route('customer.account.phone.confirm') }}" class="space-y-3">
                        @csrf
                        <label for="code" class="block text-sm font-medium">{{ __('Enter the 6-digit code we texted to :phone', ['phone' => $pendingPhone]) }}</label>
                        <input id="code" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"
                            class="w-40 rounded-md border-brand-gray-300 tracking-widest text-lg">
                        <div class="flex flex-wrap items-center gap-4">
                            <button type="submit" class="px-6 py-2.5 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Confirm new number') }}</button>
                        </div>
                    </form>
                    <div class="flex flex-wrap gap-4 mt-3 text-sm">
                        <form method="POST" action="{{ route('customer.account.phone.code') }}">
                            @csrf
                            <input type="hidden" name="phone" value="{{ $pendingPhone }}">
                            <button type="submit" class="underline text-brand-gray-500">{{ __('Send the code again') }}</button>
                        </form>
                        <form method="POST" action="{{ route('customer.account.phone.cancel') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="underline text-brand-gray-500">{{ __('Keep my current number') }}</button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('customer.account.phone.code') }}" class="space-y-3"
                        x-data="{ phone: phoneField(@js(old('phone', ''))) }" @submit="if (! phone.valid) $event.preventDefault()">
                        @csrf
                        <label for="new_phone" class="block text-sm font-medium">{{ __('New phone number') }}</label>
                        <input id="new_phone" type="tel" name="phone" inputmode="numeric" autocomplete="tel" required
                            :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                            placeholder="024-123-4567" maxlength="12"
                            class="w-full sm:w-64 rounded-md" :class="phone.invalid ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300'">
                        <p class="text-xs text-brand-gray-500">{{ __('We\'ll text a code to the new number. It only changes once you enter it.') }}</p>
                        <button type="submit" class="px-6 py-2.5 border border-brand-gray-300 font-semibold rounded-md hover:bg-brand-gray-100">{{ __('Send code') }}</button>
                    </form>
                @endif
            </section>

            <section class="border border-brand-gray-100 rounded-lg p-5">
                <h2 class="font-bold mb-4">{{ __('Change password') }}</h2>
                <form method="POST" action="{{ route('customer.account.password') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    @foreach (['current_password' => [__('Current password'), 'current-password'], 'password' => [__('New password (at least 8 characters)'), 'new-password'], 'password_confirmation' => [__('Confirm new password'), 'new-password']] as $field => [$label, $autocomplete])
                        <div>
                            <label for="{{ $field }}" class="block text-sm font-medium mb-1">{{ $label }} <span class="text-brand-red">*</span></label>
                            <input id="{{ $field }}" type="password" name="{{ $field }}" required autocomplete="{{ $autocomplete }}"
                                class="w-full sm:w-80 rounded-md {{ $errors->password->has($field) ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}">
                            @if ($errors->password->has($field)) <p class="text-sm text-brand-red mt-1">{{ $errors->password->first($field) }}</p> @endif
                        </div>
                    @endforeach
                    <button type="submit" class="px-6 py-2.5 border border-brand-gray-300 font-semibold rounded-md hover:bg-brand-gray-100">{{ __('Change password') }}</button>
                </form>
            </section>

            <section class="border border-brand-gray-100 rounded-lg p-5">
                <h2 class="font-bold mb-1">{{ __('SMS offers') }}</h2>
                <p class="text-sm text-brand-gray-500 mb-4">{{ __('Deals and news by text. Order updates are always texted, whatever you choose here.') }}</p>
                <form method="POST" action="{{ route('customer.account.sms-offers') }}" class="flex flex-wrap items-center gap-4">
                    @csrf
                    @method('PUT')
                    @if ($customer->marketing_opted_out_at)
                        <span class="text-sm">{{ __('You\'re not getting SMS offers.') }}</span>
                        <input type="hidden" name="sms_offers" value="1">
                        <button type="submit" class="px-4 py-2 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Send me offers') }}</button>
                    @else
                        <span class="text-sm">{{ __('You\'re getting SMS offers.') }}</span>
                        <input type="hidden" name="sms_offers" value="0">
                        <button type="submit" class="px-4 py-2 border border-brand-gray-300 text-sm font-semibold rounded-md hover:bg-brand-gray-100">{{ __('Stop offers') }}</button>
                    @endif
                </form>
            </section>
        </div>
    </div>

    @include('partials.phone-input-script')
</x-customer-layout>
