{{--
    Staff ID + PIN at a counter (Express POS step 7b) — only shown on a
    device set up as a counter. $counter is that counter.
--}}
@php
    $field = 'mt-1.5 block w-full h-12 rounded-lg border-xpos-line bg-white px-4 text-base text-xpos-midnight placeholder:text-xpos-muted focus:border-xpos-midnight focus:ring-2 focus:ring-xpos-gold';
@endphp

@include('auth.partials.sign-in-header', ['subheading' => __(':counter · sign in to open the till.', ['counter' => $counter->name])])

<form method="POST" action="{{ route('login.pin') }}" class="mt-8 space-y-5">
    @csrf

    <div>
        <label for="staff_code" class="block text-sm font-semibold">{{ __('Staff ID') }}</label>
        <input id="staff_code" name="staff_code" type="text" value="{{ old('staff_code') }}" required autofocus autocomplete="off" autocapitalize="characters" spellcheck="false"
            placeholder="{{ __('e.g. :code', ['code' => app(\App\Services\Users\StaffCodeService::class)->prefix().'-0042']) }}" class="{{ $field }} uppercase placeholder:normal-case">
        <x-input-error :messages="$errors->get('staff_code')" class="mt-2" />
    </div>

    <div>
        <label for="pin" class="block text-sm font-semibold">{{ __('PIN') }}</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" required autocomplete="off"
            placeholder="• • • •" class="{{ $field }} tracking-[0.4em]">
        <x-input-error :messages="$errors->get('pin')" class="mt-2" />
    </div>

    <button type="submit" class="w-full h-12 rounded-lg bg-xpos-midnight text-xpos-gold font-bold text-base hover:bg-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-xpos-paper focus:ring-xpos-gold">
        {{ __('Open till') }}
    </button>

    <p class="text-center text-sm text-gray-600">{{ __('Forgot your PIN? Ask a manager to reset it.') }}</p>
</form>
