{{--
    The right-hand side of the Photo-led and One-page headers: account link,
    cart with its count, the theme's order button and the phone menu button
    (which opens the shared drawer in layouts/customer). $orderUrl is where
    "Order now" goes.
--}}
<div class="flex items-center gap-2 sm:gap-4">
    @auth('customer')
        <a href="{{ route('customer.account.show') }}" class="hidden lg:inline text-sm font-medium text-brand-gray-700 hover:text-brand-black">{{ __('My account') }}</a>
    @else
        <a href="{{ route('customer.login') }}" class="hidden lg:inline text-sm font-medium text-brand-gray-700 hover:text-brand-black">{{ __('Login') }}</a>
    @endauth

    <a href="{{ route('cart.show') }}" class="relative p-2 text-brand-black hover:opacity-70" aria-label="{{ __('Cart') }} ({{ $cartItemCount }})">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
            <path d="M3 6h18" />
            <path d="M16 10a4 4 0 0 1-8 0" />
        </svg>
        @if ($cartItemCount > 0)
            <span class="absolute top-0 right-0 bg-brand-red text-brand-on-accent text-[11px] font-bold rounded-full min-w-5 h-5 px-1 flex items-center justify-center">{{ $cartItemCount }}</span>
        @endif
    </a>

    <a href="{{ $orderUrl }}" class="hidden sm:inline-flex items-center px-5 py-2.5 rounded-full bg-brand-yellow text-brand-on-primary text-sm font-semibold hover:bg-brand-yellow-dark transition">{{ __('Order now') }}</a>

    <button
        type="button" class="md:hidden p-2 -me-2 text-brand-black touch-manipulation" @click="open = true; $dispatch('customer-drawer-opened')"
        aria-label="{{ __('Open menu') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
            <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
</div>
