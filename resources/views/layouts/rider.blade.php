<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Rider') }} · {{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link
            href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap"
            rel="stylesheet"
            integrity="sha384-lQM68ivKBOfXgJDga3JtrfUEh8B2uRHapweBL5h4+Dz79cLzvpVQ5pDQbFa9Mlh4"
            crossorigin="anonymous"
        />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{--
            Deliberately not the shared staff layouts.navigation — that
            bakes in hardcoded links to route('dashboard') and
            route('logout') (the staff guard's), which would pull a rider
            straight back into the staff order board. Riders get their own
            section end to end, not just their own routes.
        --}}
        <div class="min-h-screen flex bg-gray-100" x-data="{ sidebarOpen: false }">
            <!-- Desktop sidebar -->
            <aside class="hidden sm:flex sm:flex-col sm:w-64 sm:shrink-0 sm:sticky sm:top-0 sm:h-screen bg-white border-r border-gray-100">
                <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
                    <a href="{{ route('rider.dashboard') }}">
                        <x-application-logo class="block h-9 w-auto" />
                    </a>
                </div>

                <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                    @include('layouts.rider-navigation-links')
                </nav>

                <div class="border-t border-gray-100 p-4 shrink-0 text-sm" x-data="shiftWidget()" x-init="init()">
                    <div x-show="active" class="text-gray-500 mb-2">
                        {{ __('On shift') }} <span x-text="branch"></span>
                    </div>
                    <button
                        type="button" x-show="!active" @click="start()"
                        class="w-full px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900 mb-3"
                    >{{ __('Start shift') }}</button>
                    <button
                        type="button" x-show="active" @click="end()"
                        class="w-full px-3 py-1.5 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300 mb-3"
                    >{{ __('End shift') }}</button>

                    <div class="text-gray-800 font-medium truncate">{{ Auth::user()?->name }}</div>
                    <form method="POST" action="{{ route('rider.logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-500 hover:text-gray-800">{{ __('Log out') }}</button>
                    </form>
                </div>
            </aside>

            <!-- Mobile slide-out drawer -->
            <div x-show="sidebarOpen" x-cloak class="sm:hidden fixed inset-0 z-40" role="dialog" aria-modal="true">
                <div
                    class="fixed inset-0 bg-black/50"
                    x-show="sidebarOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="sidebarOpen = false"
                ></div>

                <div
                    class="relative w-64 h-full bg-white flex flex-col"
                    x-show="sidebarOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                >
                    <div class="h-16 flex items-center justify-between px-4 border-b border-gray-100 shrink-0">
                        <a href="{{ route('rider.dashboard') }}">
                            <x-application-logo class="block h-9 w-auto" />
                        </a>
                        <button @click="sidebarOpen = false" aria-label="{{ __('Close menu') }}" class="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1" @click="sidebarOpen = false">
                        @include('layouts.rider-navigation-links')
                    </nav>

                    <div class="border-t border-gray-100 p-4 shrink-0 text-sm">
                        <div class="text-gray-800 font-medium">{{ Auth::user()?->name }}</div>
                        <form method="POST" action="{{ route('rider.logout') }}">
                            @csrf
                            <button type="submit" class="text-gray-500 hover:text-gray-800 mt-2">{{ __('Log out') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="flex-1 flex flex-col min-w-0">
                <!-- Mobile top bar -->
                <div class="sm:hidden flex items-center justify-between h-16 px-4 bg-white border-b border-gray-100 shrink-0" x-data="shiftWidget()" x-init="init()">
                    <div class="flex items-center gap-1">
                        <button @click="sidebarOpen = true" aria-label="{{ __('Open menu') }}" class="p-2 -ms-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <a href="{{ route('rider.dashboard') }}">
                            <x-application-logo class="block h-8 w-auto" />
                        </a>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <span x-show="active" class="text-gray-500">{{ __('On shift') }}</span>
                        <button
                            type="button" x-show="!active" @click="start()"
                            class="px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900"
                        >{{ __('Start shift') }}</button>
                        <button
                            type="button" x-show="active" @click="end()"
                            class="px-3 py-1.5 bg-gray-200 text-gray-800 text-xs font-semibold rounded-md hover:bg-gray-300"
                        >{{ __('End shift') }}</button>
                    </div>
                </div>

                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @include('partials.shift-widget-script')
    </body>
</html>
