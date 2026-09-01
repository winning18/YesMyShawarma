<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add user') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4">
        <form
            method="POST" action="{{ route('dashboard.users.store') }}" class="bg-white shadow rounded-lg p-6 space-y-6"
            x-data="{ phone: phoneField(@js(old('phone', ''))) }"
        >
            @csrf

            <div>
                <x-input-label for="name" :value="__('Name')" required />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" required />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>

            <div>
                <x-input-label for="phone" :value="__('Phone number (optional)')" />
                <input
                    id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel"
                    :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                    placeholder="024-123-4567" maxlength="12"
                    class="mt-1 block w-full rounded-md shadow-sm"
                    :class="phone.raw.length > 0 && !phone.valid ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'"
                >
                <p class="text-xs text-red-600 mt-1" x-show="phone.raw.length > 0 && !phone.valid">{{ __('Enter a 10-digit phone number, or leave it blank.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>

            <div>
                <x-input-label for="role" :value="__('Role')" required />
                <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300" required>
                    <option value="">{{ __('Select a role…') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role') === $role)>{{ str($role)->headline() }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('role')" />
            </div>

            <div>
                <x-input-label for="branch_id" :value="__('Branch')" required />
                <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                    <option value="">{{ __('Select a branch…') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('branch_id')" />
            </div>

            <p class="text-sm text-gray-500">
                {{ __("A one-time password will be generated for them. You'll see it once on the next screen to pass along yourself.") }}
            </p>

            <x-primary-button>{{ __('Create user') }}</x-primary-button>
        </form>
    </div>

    @include('partials.phone-input-script')
</x-app-layout>
