<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-customer antialiased {{ $bodyClass }} text-brand-black">
        <header class="bg-brand-black" x-data="{ open: false }">
            <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
                <a href="{{ route('home') }}">
                    <img src="{{ asset('images/logo-web.png') }}" alt="{{ config('app.name') }}" class="h-10 w-auto">
                </a>

                <nav class="hidden md:flex items-center gap-6 text-sm text-brand-white">
                    <a href="{{ route('home') }}" class="hover:text-brand-yellow">{{ __('Home') }}</a>
                    <a href="{{ route('menu.index') }}" class="hover:text-brand-yellow">{{ __('Menu') }}</a>
                    <a href="{{ route('branches.index') }}" class="hover:text-brand-yellow">{{ __('Branches') }}</a>
                    <a href="{{ route('contact') }}" class="hover:text-brand-yellow">{{ __('Contact us') }}</a>
                    <a href="{{ route('about') }}" class="hover:text-brand-yellow">{{ __('About us') }}</a>
                    <a href="{{ route('tracking.lookup') }}" class="hover:text-brand-yellow">{{ __('Track order') }}</a>
                </nav>

                <div class="hidden md:flex items-center gap-4">
                    <a href="{{ route('cart.show') }}" class="relative text-brand-white hover:text-brand-yellow text-sm">
                        {{ __('Cart') }}
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
                <a href="{{ route('home') }}" class="block hover:text-brand-yellow">{{ __('Home') }}</a>
                <a href="{{ route('menu.index') }}" class="block hover:text-brand-yellow">{{ __('Menu') }}</a>
                <a href="{{ route('branches.index') }}" class="block hover:text-brand-yellow">{{ __('Branches') }}</a>
                <a href="{{ route('contact') }}" class="block hover:text-brand-yellow">{{ __('Contact us') }}</a>
                <a href="{{ route('about') }}" class="block hover:text-brand-yellow">{{ __('About us') }}</a>
                <a href="{{ route('tracking.lookup') }}" class="block hover:text-brand-yellow">{{ __('Track order') }}</a>
                <a href="{{ route('cart.show') }}" class="block hover:text-brand-yellow">{{ __('Cart') }} ({{ $cartItemCount }})</a>
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

        @if (session('status'))
            <div class="max-w-5xl mx-auto px-4 pt-4">
                <div class="rounded-lg bg-brand-yellow-light border border-brand-yellow text-brand-black text-sm px-4 py-2">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        <main class="max-w-5xl mx-auto px-4 py-8">
            {{ $slot }}
        </main>

        <footer class="mt-12 bg-brand-white border-t border-brand-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm text-brand-gray-500">
                <div>
                    <img src="{{ asset('images/logo-web.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto mb-3">
                    <a href="{{ route('contact') }}" class="block hover:text-brand-black">{{ __('Contact us') }}</a>
                    <a href="{{ route('about') }}" class="block hover:text-brand-black">{{ __('About us') }}</a>
                </div>
                <div>
                    <p class="font-semibold text-brand-black mb-2">{{ __('Follow us') }}</p>
                    <x-social-links icon-class="text-brand-gray-500" />
                </div>
                <div>
                    <p class="font-semibold text-brand-black mb-2">{{ __('Call us') }}</p>
                    <a href="tel:+233243635265" class="block hover:text-brand-black"><span class="font-bold">Ga Odumase</span>: +233 (0) 243 635 265</a>
                    <a href="tel:+233531907747" class="block hover:text-brand-black"><span class="font-bold">Pokuase</span>: +233 (0) 531 907 747</a>
                </div>
            </div>
            <div class="max-w-5xl mx-auto px-4 py-4 border-t border-brand-gray-100 text-sm text-brand-gray-500 text-center">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </div>
        </footer>
    </body>
</html>
