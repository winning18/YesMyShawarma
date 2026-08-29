<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        {{--
            Link-preview tags — without these, WhatsApp/Facebook/iMessage
            etc fall back to grabbing the first <img> on the page (the
            header logo), which is why every shared link used to show the
            same logo regardless of what it linked to. $ogImage/
            $ogDescription are per-page (see CustomerLayout — a product
            page passes its own photo); the fallback here is the one
            default used by every page that doesn't set its own.
        --}}
        @php
            $ogImageUrl = $ogImage ?? asset('images/logo-web.png');
            $ogDescriptionText = $ogDescription ?? __('Shawarma, burgers and more — order online for pickup from our Accra branches.');
        @endphp
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $ogDescriptionText }}">
        <meta property="og:image" content="{{ $ogImageUrl }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
        <meta name="twitter:description" content="{{ $ogDescriptionText }}">
        <meta name="twitter:image" content="{{ $ogImageUrl }}">

        <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-customer antialiased {{ $bodyClass }} text-brand-black min-h-screen flex flex-col">
        {{--
            Full-screen page loader. No real vector source exists for the
            logo (only the PNG exports), so this uses the real logo image
            as-is plus a hand-authored SVG spinner rather than a fabricated
            vector trace of the wordmark. Waits for window's load event
            (all assets, not just the DOM) before fading out; if that's
            already fired by the time Alpine attaches (fast/cached loads),
            it just fades immediately instead of waiting forever.
        --}}
        <div
            x-data="{ show: true }"
            x-init="document.readyState === 'complete'
                ? setTimeout(() => show = false, 200)
                : window.addEventListener('load', () => setTimeout(() => show = false, 200))"
            x-show="show"
            x-transition:leave="transition ease-out duration-300"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 bg-brand-black flex flex-col items-center justify-center gap-6"
        >
            <img src="{{ asset('images/logo-web.png') }}" alt="{{ config('app.name') }}" class="h-16 w-auto">
            <svg viewBox="0 0 24 24" fill="none" class="animate-spin w-8 h-8 text-brand-yellow">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
            </svg>
        </div>

        <header class="bg-brand-black sticky top-0 z-40" x-data="{ open: false }">
            <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
                <a href="{{ route('home') }}">
                    <img src="{{ asset('images/logo-web.png') }}" alt="{{ config('app.name') }}" class="h-10 w-auto">
                </a>

                <nav class="hidden md:flex items-center gap-6 text-sm text-brand-white">
                    <a href="{{ route('home') }}" class="hover:text-brand-yellow {{ request()->routeIs('home') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Home') }}</a>
                    <a href="{{ route('menu.index') }}" class="hover:text-brand-yellow {{ request()->routeIs('menu.index') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Menu') }}</a>
                    <a href="{{ route('branches.index') }}" class="hover:text-brand-yellow {{ request()->routeIs('branches.index') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Branches') }}</a>
                    <a href="{{ route('contact') }}" class="hover:text-brand-yellow {{ request()->routeIs('contact') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Contact us') }}</a>
                    <a href="{{ route('about') }}" class="hover:text-brand-yellow {{ request()->routeIs('about') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('About us') }}</a>
                    <a href="{{ route('tracking.lookup') }}" class="hover:text-brand-yellow {{ request()->routeIs('tracking.*') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Track order') }}</a>
                </nav>

                <div class="hidden md:flex items-center gap-4">
                    <a href="{{ route('cart.show') }}" class="relative text-brand-white hover:text-brand-yellow" aria-label="{{ __('Cart') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        @if($cartItemCount > 0)
                            <span class="absolute -top-2 -right-3 bg-brand-yellow text-brand-black text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ $cartItemCount }}</span>
                        @endif
                    </a>

                    @auth('customer')
                        <span class="text-brand-white text-sm">{{ auth('customer')->user()->name ?? auth('customer')->user()->phone }}</span>
                        <form method="POST" action="{{ route('customer.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-brand-white hover:text-brand-yellow">{{ __('Log out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('customer.login') }}" class="text-sm text-brand-white hover:text-brand-yellow">{{ __('Login') }}</a>
                        <a href="{{ route('customer.register') }}" class="px-3 py-1.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Sign up') }}</a>
                    @endauth
                </div>

                <button type="button" class="md:hidden text-brand-white" @click="open = !open">
                    &#9776;
                </button>
            </div>

            <nav x-show="open" x-cloak class="md:hidden px-4 pb-4 space-y-2 text-brand-white text-sm">
                <a href="{{ route('home') }}" class="block hover:text-brand-yellow {{ request()->routeIs('home') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Home') }}</a>
                <a href="{{ route('menu.index') }}" class="block hover:text-brand-yellow {{ request()->routeIs('menu.index') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Menu') }}</a>
                <a href="{{ route('branches.index') }}" class="block hover:text-brand-yellow {{ request()->routeIs('branches.index') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Branches') }}</a>
                <a href="{{ route('contact') }}" class="block hover:text-brand-yellow {{ request()->routeIs('contact') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Contact us') }}</a>
                <a href="{{ route('about') }}" class="block hover:text-brand-yellow {{ request()->routeIs('about') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('About us') }}</a>
                <a href="{{ route('tracking.lookup') }}" class="block hover:text-brand-yellow {{ request()->routeIs('tracking.*') ? 'text-brand-yellow font-semibold' : '' }}">{{ __('Track order') }}</a>
                <a href="{{ route('cart.show') }}" class="flex items-center gap-2 hover:text-brand-yellow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    {{ __('Cart') }} ({{ $cartItemCount }})
                </a>
                @auth('customer')
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button type="submit" class="hover:text-brand-yellow">{{ __('Log out') }}</button>
                    </form>
                @else
                    <a href="{{ route('customer.login') }}" class="block hover:text-brand-yellow">{{ __('Login') }}</a>
                    <a href="{{ route('customer.register') }}" class="block hover:text-brand-yellow">{{ __('Sign up') }}</a>
                @endauth
            </nav>
        </header>

        @isset($fullHero)
            {{ $fullHero }}
        @endisset

        @isset($pageHeader)
            <div class="bg-brand-black py-10 border-t border-brand-yellow">
                <div class="max-w-5xl mx-auto px-4">
                    <h1 class="text-2xl font-bold text-brand-white text-center">{{ $pageHeader }}</h1>
                </div>
            </div>
        @endisset

        @if (session('status'))
            <div class="max-w-5xl mx-auto px-4 pt-4">
                <div class="rounded-lg bg-brand-yellow-light border border-brand-yellow text-brand-black text-sm px-4 py-2">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @if (session('added_to_cart'))
            <div x-data="{ show: true }" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="show = false"></div>
                <div class="relative bg-brand-white rounded-lg shadow-lg max-w-sm w-full p-6 text-center">
                    <h2 class="text-lg font-bold text-brand-black mb-1">{{ __('Added to cart') }}</h2>
                    <p class="text-sm text-brand-gray-500 mb-5">{{ __('What would you like to do next?') }}</p>
                    <div class="grid grid-cols-1 gap-3">
                        <a href="{{ route('cart.show') }}" class="w-full px-4 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">
                            {{ __('Go to cart') }}
                        </a>
                        <button type="button" @click="show = false" class="w-full px-4 py-2.5 border border-brand-gray-300 text-sm font-semibold rounded-md hover:bg-brand-gray-100">
                            {{ __('Continue shopping') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <main class="{{ $mainClass }} w-full mx-auto px-4 py-8 flex-1">
            {{ $slot }}
        </main>

        <footer class="mt-12 bg-brand-black border-t border-brand-yellow">
            <div class="max-w-5xl mx-auto px-4 py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 text-sm text-brand-gray-300">
                <div>
                    <img src="{{ asset('images/logo-web.png') }}" alt="{{ config('app.name') }}" class="h-9 w-auto mb-4">
                    <p class="text-brand-gray-300">
                        {{ __('Shawarma, burgers and more — order online for pickup from our Accra branches.') }}
                    </p>
                </div>

                <div>
                    <p class="font-semibold text-brand-yellow uppercase text-xs tracking-wide mb-4">{{ __('Quick links') }}</p>
                    <nav class="space-y-2">
                        <a href="{{ route('home') }}" class="block hover:text-brand-yellow transition">{{ __('Home') }}</a>
                        <a href="{{ route('menu.index') }}" class="block hover:text-brand-yellow transition">{{ __('Menu') }}</a>
                        <a href="{{ route('branches.index') }}" class="block hover:text-brand-yellow transition">{{ __('Branches') }}</a>
                        <a href="{{ route('contact') }}" class="block hover:text-brand-yellow transition">{{ __('Contact us') }}</a>
                        <a href="{{ route('about') }}" class="block hover:text-brand-yellow transition">{{ __('About us') }}</a>
                        <a href="{{ route('tracking.lookup') }}" class="block hover:text-brand-yellow transition">{{ __('Track order') }}</a>
                        <a href="{{ route('faq') }}" class="block hover:text-brand-yellow transition">{{ __('FAQ') }}</a>
                        <a href="{{ route('reviews.index') }}" class="block hover:text-brand-yellow transition">{{ __('Reviews') }}</a>
                    </nav>
                </div>

                <div>
                    <p class="font-semibold text-brand-yellow uppercase text-xs tracking-wide mb-4">{{ __('Follow us') }}</p>
                    <x-social-links icon-class="text-brand-gray-300" />
                </div>

                <div>
                    <p class="font-semibold text-brand-yellow uppercase text-xs tracking-wide mb-4">{{ __('Call us') }}</p>
                    <a href="tel:+233243635265" class="block hover:text-brand-yellow transition mb-2"><span class="font-bold text-brand-white">Ga Odumase</span><br>+233 (0) 243 635 265</a>
                    <a href="tel:+233531907747" class="block hover:text-brand-yellow transition"><span class="font-bold text-brand-white">Pokuase</span><br>+233 (0) 531 907 747</a>
                </div>
            </div>
            <div class="max-w-5xl mx-auto px-4 py-4 border-t border-brand-gray-700 text-sm text-brand-gray-300 text-center">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </div>
        </footer>
    </body>
</html>
