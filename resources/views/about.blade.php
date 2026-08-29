<x-customer-layout title="About us · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('About us') }}</x-slot>

    {{-- Mission and vision --}}
    <section class="mb-14">
        <div class="space-y-8">
            <div>
                <p class="font-semibold text-brand-yellow-dark uppercase text-xs tracking-wide mb-3">{{ __('Mission') }}</p>
                <p class="text-brand-black">{{ __("We're here to turn a simple craving into a reliable favourite, delivering bold, quality meals quickly and consistently across every neighbourhood we reach.") }}</p>
            </div>
            <div>
                <p class="font-semibold text-brand-yellow-dark uppercase text-xs tracking-wide mb-3">{{ __('Vision') }}</p>
                <p class="text-brand-black">{{ __('To be the first name Ghanaians think of when they crave quality authentic meals, growing from Accra across the country and beyond.') }}</p>
            </div>
        </div>
    </section>

    {{-- Our success story --}}
    <section class="mb-14 pt-14 border-t border-brand-gray-100">
        <h2 class="text-lg font-bold mb-6 text-center">{{ __('Our success story') }}</h2>

        <div class="text-brand-black leading-relaxed space-y-4 mb-10">
            <p>{{ __('Yes! My Shawarma began as a bold idea inside Yes! My Grill to serve exceptional shawarma wraps built on two things too often missing from fast food: real health and true value for money.') }}</p>
            <p>{{ __("We started small. One joint, one conviction: that a shawarma could be crafted, not just assembled. Our wraps tasted different, and at first, different drew questions. Some customers weren't sure what to make of a flavour that didn't copy everyone else. But we kept serving, kept explaining, and something began to shift. One bite at a time, people understood, this wasn't just another wrap. This was a recipe with a craft behind it, a quality they couldn't get anywhere else. The questions turned into cravings, and the first-timers turned into regulars.") }}</p>
            <p>{{ __("Those returning customers gave us our first real hope, and they've carried us ever since. We served over 5,000 in-house orders before our second branch even opened, launching in December and opening our second location by March. Today we run three branches and counting, with more strategic locations on the way to bring our wraps to even more of Accra.") }}</p>
            <p>{{ __("Along the way, we've built more than a menu. We've created jobs for over many Ghanaian youth, and together we show up every day to deliver the same thing: the best service, aimed squarely at one goal, a customer who leaves satisfied and comes back for more.") }}</p>
            <p>{{ __("We're famous for our recipe, and we're proud of it. We're not like every other food joint. Our craft is our own and that's exactly the point. We're just getting started.") }}</p>
            <p class="font-semibold">{{ __("One bite, and you'll want more.") }}</p>
        </div>

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
