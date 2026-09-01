<x-customer-layout title="Contact us · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Contact us') }}</x-slot>

    {{-- Branch locations — same card component as the Branches page
         (x-branch-card), so a design or data change there applies here
         too rather than the two drifting apart. --}}
    <h2 class="text-lg font-bold mb-4">{{ __('Our locations') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        @foreach ($branches as $branch)
            <x-branch-card :branch="$branch" :selected="$selectedBranchId === $branch->id" />
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Send us a message --}}
        <div>
            <h2 class="text-2xl font-bold mb-4">{{ __('Send us a message') }}</h2>

            <form
                method="POST" action="{{ route('contact.submit') }}" class="space-y-4"
                x-data="{
                    email: @js(old('email', '')),
                    phone: @js(old('phone', '')),
                    get missingContact() { return this.email.trim() === '' && this.phone.trim() === ''; },
                }"
                @submit="if (missingContact) $event.preventDefault();"
            >
                @csrf
                <x-honeypot />

                <div>
                    <label for="name" class="block text-sm font-medium mb-1">{{ __('Name') }}</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="w-full rounded-md border-brand-gray-300 focus:border-brand-yellow focus:ring-brand-yellow">
                    @error('name') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1">{{ __('Email') }}</label>
                        <input type="email" name="email" id="email" x-model="email"
                            class="w-full rounded-md border-brand-gray-300 focus:border-brand-yellow focus:ring-brand-yellow">
                        @error('email') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium mb-1">{{ __('Phone') }}</label>
                        <input type="tel" name="phone" id="phone" x-model="phone"
                            class="w-full rounded-md border-brand-gray-300 focus:border-brand-yellow focus:ring-brand-yellow">
                        @error('phone') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="text-xs -mt-2" :class="missingContact ? 'text-brand-red' : 'text-brand-gray-500'">{{ __('Provide at least an email or a phone number so we can reply.') }}</p>

                <div>
                    <label for="message" class="block text-sm font-medium mb-1">{{ __('Message') }}</label>
                    <textarea name="message" id="message" rows="5" required maxlength="2000"
                        class="w-full rounded-md border-brand-gray-300 focus:border-brand-yellow focus:ring-brand-yellow">{{ old('message') }}</textarea>
                    @error('message') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                </div>

                <x-turnstile-widget />

                <button type="submit" class="px-6 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">
                    {{ __('Send message') }}
                </button>
            </form>
        </div>

        {{-- Call, Email, Follow — stacked top to bottom --}}
        <div class="space-y-5">
            <div class="bg-brand-black rounded-xl p-6 text-brand-white">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-brand-yellow flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-brand-black">
                            <path d="M4 5c0 8.284 6.716 15 15 15l3-3.5-5-3-2 2c-2-1-4.5-3.5-5.5-5.5l2-2-3-5L5 4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <p class="font-semibold">{{ __('Call or WhatsApp') }}</p>
                </div>
                <ul class="space-y-1.5 text-sm text-brand-gray-100">
                    <li class="flex justify-between gap-2">
                        <span class="font-bold text-brand-white">{{ __('Ga Odumase') }}</span>
                        <a href="tel:+233243635265" class="underline hover:text-brand-yellow">+233 (0) 243 635 265</a>
                    </li>
                    <li class="flex justify-between gap-2">
                        <span class="font-bold text-brand-white">{{ __('Pokuase') }}</span>
                        <a href="tel:+233531907747" class="underline hover:text-brand-yellow">+233 (0) 531 907 747</a>
                    </li>
                </ul>
            </div>

            <div class="bg-brand-black rounded-xl p-6 text-brand-white">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-brand-yellow flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-brand-black">
                            <path d="M4 6h16v12H4V6Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <p class="font-semibold">{{ __('Email') }}</p>
                </div>
                <ul class="space-y-1.5 text-sm text-brand-gray-100">
                    <li><a href="mailto:yesmyshawarma@gmail.com" class="underline hover:text-brand-yellow break-all">yesmyshawarma@gmail.com</a></li>
                    <li><a href="mailto:yesmygrill@gmail.com" class="underline hover:text-brand-yellow break-all">yesmygrill@gmail.com</a></li>
                </ul>
            </div>

            <div class="bg-brand-black rounded-xl p-6 text-brand-white">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-brand-yellow flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-brand-black">
                            <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.5" />
                            <path d="M4 12h16M12 4c2.5 2.5 2.5 13.5 0 16M12 4c-2.5 2.5-2.5 13.5 0 16" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                    </div>
                    <p class="font-semibold">{{ __('Follow us') }}</p>
                </div>
                <x-social-links icon-class="text-brand-gray-100" />
            </div>
        </div>
    </div>
</x-customer-layout>
