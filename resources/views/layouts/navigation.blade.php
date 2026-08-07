{{-- Desktop sidebar. sidebarOpen lives on the wrapping div in app.blade.php, shared with the mobile top bar's hamburger button. --}}
<aside class="hidden sm:flex sm:flex-col sm:w-64 sm:shrink-0 sm:sticky sm:top-0 sm:h-screen bg-white border-r border-gray-100">
    <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
        <a href="{{ route('dashboard') }}">
            <x-application-logo class="block h-9 w-auto" />
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        @include('layouts.navigation-links')
    </nav>

    <div class="border-t border-gray-100 p-3 shrink-0">
        <x-dropdown align="top" width="56">
            <x-slot name="trigger">
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 rounded-md text-sm text-gray-600 hover:bg-gray-50 focus:outline-none">
                    <span class="truncate">{{ Auth::user()->name }}</span>
                    <svg class="fill-current h-4 w-4 shrink-0 ms-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-dropdown-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-dropdown-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</aside>

{{-- Mobile slide-out drawer --}}
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
            <a href="{{ route('dashboard') }}">
                <x-application-logo class="block h-9 w-auto" />
            </a>
            <button @click="sidebarOpen = false" aria-label="{{ __('Close menu') }}" class="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1" @click="sidebarOpen = false">
            @include('layouts.navigation-links')
        </nav>

        <div class="border-t border-gray-100 p-4 shrink-0">
            <div class="font-medium text-sm text-gray-800">{{ Auth::user()->name }}</div>
            <div class="font-medium text-sm text-gray-500 mb-3">{{ Auth::user()->email }}</div>

            <div class="space-y-1">
                <x-sidebar-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-sidebar-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-sidebar-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-sidebar-link>
                </form>
            </div>
        </div>
    </div>
</div>
