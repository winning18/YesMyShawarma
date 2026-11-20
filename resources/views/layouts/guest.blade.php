<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        {{--
            The staff and rider sign-in pages are Express POS's own (CLAUDE.md
            product rule 6): its logo, colours and display face, never the
            business's. Everything after sign-in is the business's branding.
        --}}
        <title>{{ __('Sign in') }} · Express POS</title>
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect width="48" height="48" rx="10" fill="#101010"/><circle cx="12" cy="24" r="4" fill="#fff"/><path d="M18.4 16.9a10 10 0 0 1 0 14.2" stroke="#FCD116" stroke-width="4.5" stroke-linecap="round" fill="none"/><path d="M23.3 11.6a17 17 0 0 1 0 24.8" stroke="#12A150" stroke-width="4.5" stroke-linecap="round" fill="none"/><path d="M28.2 6.3a24 24 0 0 1 0 35.4" stroke="#E23A3A" stroke-width="4.5" stroke-linecap="round" fill="none"/></svg>') }}">

        {{-- Archivo Black Italic (SIL Open Font License), latin only, 15 KB — the wordmark and headline. --}}
        <link rel="preload" href="{{ asset('fonts/archivo-900-italic-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
        <style>
            @font-face {
                font-family: 'Archivo Express';
                src: url('{{ asset('fonts/archivo-900-italic-latin.woff2') }}') format('woff2');
                font-weight: 900;
                font-style: italic;
                font-display: swap;
            }
        </style>

        <!-- Scripts -->
        @include('partials.reverb-config')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-xpos-paper text-xpos-midnight">
        <div class="min-h-screen md:grid md:grid-cols-[minmax(20rem,26rem)_1fr]">
            <aside class="bg-xpos-midnight text-white px-6 py-6 md:px-12 md:py-12 flex flex-col">
                <div>
                    <x-express-pos-logo />
                </div>

                <div class="hidden md:block my-auto">
                    <p class="font-xpos-display italic font-black text-6xl leading-[0.95] tracking-tight">
                        {{ __('Tap.') }}<br>{{ __('Pay.') }}<br><span class="text-xpos-gold">{{ __('Go.') }}</span>
                    </p>
                    <p class="mt-6 text-lg text-gray-300 max-w-xs">{{ __('Fast checkout for cash, card and mobile money.') }}</p>
                </div>

                <x-solusys-social-links class="hidden md:block" />
            </aside>

            <main class="flex flex-col items-center justify-center px-6 py-10 md:py-12">
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
                <x-solusys-social-links class="md:hidden mt-10 w-full max-w-sm" :on-light="true" />
            </main>
        </div>
    </body>
</html>
