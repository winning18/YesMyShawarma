<x-customer-layout title="My addresses · {{ config('app.name') }}">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">{{ __('My account') }}</h1>
        @include('customer.account._nav', ['active' => 'addresses'])

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-brand-gray-500 mb-4">{{ __('Pick one of these at checkout and we fill in the rest, including your location for the delivery fee. You can also tick "Save this address" when you check out.') }}</p>

        <div class="space-y-3 mb-8">
            @forelse ($addresses as $address)
                <div class="border rounded-lg p-4 {{ $address->is_default ? 'border-brand-yellow border-2' : 'border-brand-gray-100' }}" x-data="{ editing: false }">
                    <div x-show="! editing">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">
                                    {{ $address->label ?: $address->areaName() ?: __('Address') }}
                                    @if ($address->is_default)
                                        <span class="ms-1 text-xs font-semibold bg-brand-yellow text-brand-black px-2 py-0.5 rounded-full">{{ __('Default') }}</span>
                                    @endif
                                </p>
                                <p class="text-sm text-brand-gray-700">{{ collect([$address->areaName(), $address->landmark])->filter()->join(' · ') }}</p>
                                @if ($address->ghanapost_code) <p class="text-sm text-brand-gray-500">{{ $address->ghanapost_code }}</p> @endif
                                <p class="text-xs mt-1 {{ $address->lat !== null ? 'text-green-700' : 'text-brand-gray-500' }}">
                                    {{ $address->lat !== null ? __('Location saved') : __('No location saved: you\'ll share it at checkout') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 mt-3 text-sm">
                            <button type="button" @click="editing = true" class="underline">{{ __('Edit') }}</button>
                            @unless ($address->is_default)
                                <form method="POST" action="{{ route('customer.account.addresses.default', $address) }}">
                                    @csrf
                                    <button type="submit" class="underline">{{ __('Make default') }}</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('customer.account.addresses.destroy', $address) }}" onsubmit="return confirm({{ Js::from(__('Remove this address?')) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="underline text-brand-red">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </div>
                    <div x-show="editing" x-cloak>
                        @include('customer.account._address-form', [
                            'action' => route('customer.account.addresses.update', $address),
                            'method' => 'PUT',
                            'address' => $address,
                            'submitLabel' => __('Save address'),
                        ])
                        <button type="button" @click="editing = false" class="mt-2 text-sm underline text-brand-gray-500">{{ __('Cancel') }}</button>
                    </div>
                </div>
            @empty
                <p class="text-brand-gray-500">{{ __('No saved addresses yet.') }}</p>
            @endforelse
        </div>

        <section class="border border-brand-gray-100 rounded-lg p-5">
            <h2 class="font-bold mb-1">{{ __('Add an address') }}</h2>
            <p class="text-sm text-brand-gray-500 mb-4">{{ __('Best done while you\'re at that address, so the saved location is right.') }}</p>
            @include('customer.account._address-form', [
                'action' => route('customer.account.addresses.store'),
                'method' => 'POST',
                'address' => null,
                'submitLabel' => __('Add address'),
            ])
        </section>
    </div>
</x-customer-layout>
