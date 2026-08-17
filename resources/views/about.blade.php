<x-customer-layout title="About us · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('About us') }}</x-slot>

    {{-- About the business --}}
    <section class="mb-14 max-w-3xl mx-auto">
        <h2 class="text-lg font-bold mb-4 text-center">{{ __('Our story') }}</h2>
        {{--
            Placeholder copy — no real founding story has been provided yet.
            Bracketed on purpose so it reads as unfinished rather than as an
            asserted fact about the business. Replace with the real text
            whenever it's ready.
        --}}
        <div class="text-brand-black text-center space-y-4">
            <p>
                [{{ __('Add your founding story here — how :name started, what makes the food and the branches special, and what the business stands for.', ['name' => config('app.name')]) }}]
            </p>
            <p>
                {{ __(':name serves shawarma, burgers, sandwiches, hot dogs, loaded fries and drinks from branches in Accra — Ga Odumase and Pokuase Y-Junction.', ['name' => config('app.name')]) }}
            </p>
        </div>
    </section>

    {{-- Our success story --}}
    <section class="mb-14 pt-14 border-t border-brand-gray-100">
        <h2 class="text-lg font-bold mb-4 text-center">{{ __('Our success story') }}</h2>

        {{-- Placeholder — same bracketed convention as "Our story" above. --}}
        <p class="text-center text-brand-gray-500 text-sm mb-6 max-w-2xl mx-auto">
            [{{ __('Add a closing line here about your growth and what keeps customers coming back.') }}]
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-brand-black rounded-xl p-6 text-brand-white text-center">
                <div class="w-12 h-12 rounded-full bg-brand-yellow flex items-center justify-center mx-auto mb-4">
                    <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-brand-black">
                        <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.5" />
                        <path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        <circle cx="17" cy="9" r="2.3" stroke="currentColor" stroke-width="1.5" />
                        <path d="M14.5 19c.3-2.3 2-4 4-4.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </div>
                <p class="text-2xl font-bold">{{ $customerCountLabel }}</p>
                <p class="text-sm text-brand-gray-300">{{ __('Happy customers') }}</p>
            </div>

            <div class="bg-brand-black rounded-xl p-6 text-brand-white text-center">
                <div class="w-12 h-12 rounded-full bg-brand-yellow flex items-center justify-center mx-auto mb-4">
                    <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-brand-black">
                        <rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.5" />
                        <path d="M4 9h16M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </div>
                <p class="text-2xl font-bold">{{ $yearsOfOperation }}</p>
                <p class="text-sm text-brand-gray-300">{{ __('Years of operation') }}</p>
            </div>

            <div class="bg-brand-black rounded-xl p-6 text-brand-white text-center">
                <div class="w-12 h-12 rounded-full bg-brand-yellow flex items-center justify-center mx-auto mb-4">
                    <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-brand-black">
                        <path d="M12 21s7-6.5 7-11.5a7 7 0 1 0-14 0C5 14.5 12 21 12 21Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        <circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                </div>
                <p class="text-2xl font-bold">{{ $branchCount }}</p>
                <p class="text-sm text-brand-gray-300">{{ __('Branches') }}</p>
            </div>
        </div>
    </section>

    {{--
        Meet our staff — real roster (StaffMemberManagementController,
        managed from Settings > Staff members), same "only show what
        actually exists" convention as the home page hero (which only
        shows categories with an uploaded photo). No placeholders once
        this exists: the section simply doesn't render until someone's
        been added.
    --}}
    @if ($staffMembers->isNotEmpty())
        <section class="mb-14 pt-14 border-t border-brand-gray-100">
            <h2 class="text-lg font-bold mb-4 text-center">{{ __('Meet our staff') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                @foreach ($staffMembers as $staffMember)
                    <div>
                        @if ($staffMember->photoUrl())
                            <img
                                src="{{ $staffMember->photoUrl() }}" alt="{{ $staffMember->name }}" loading="lazy"
                                class="aspect-square w-full object-cover rounded-xl bg-brand-gray-100"
                            >
                        @else
                            <div class="aspect-square rounded-xl bg-brand-gray-100 flex items-center justify-center">
                                <svg viewBox="0 0 24 24" fill="none" class="w-16 h-16 text-brand-gray-300">
                                    <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>
                        @endif
                        <p class="font-semibold text-center mt-3">{{ $staffMember->name }}</p>
                        @if ($staffMember->title)
                            <p class="text-sm text-brand-gray-500 text-center">{{ $staffMember->title }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Newsletter --}}
    <section class="pt-14 border-t border-brand-gray-100">
        <div class="bg-brand-black rounded-xl p-8 text-center text-brand-white">
        <h2 class="text-lg font-bold mb-2">{{ __('Join our newsletter') }}</h2>
        <p class="text-sm text-brand-gray-300 mb-6 max-w-md mx-auto">
            {{ __('Get news on new menu items, offers and branch openings — straight to your inbox.') }}
        </p>

        <form method="POST" action="{{ route('newsletter.subscribe') }}" class="flex flex-col sm:flex-row items-stretch justify-center gap-3 max-w-md mx-auto">
            @csrf
            <label for="newsletter-email" class="sr-only">{{ __('Email') }}</label>
            <input
                type="email" name="email" id="newsletter-email" value="{{ old('email') }}" required
                placeholder="{{ __('you@example.com') }}"
                class="flex-1 rounded-md border-brand-gray-300 text-brand-black focus:border-brand-yellow focus:ring-brand-yellow"
            >
            <button type="submit" class="px-6 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark shrink-0">
                {{ __('Subscribe') }}
            </button>
        </form>
        @error('email')
            <p class="text-sm text-brand-red mt-3">{{ $message }}</p>
        @enderror
        </div>
    </section>
</x-customer-layout>
