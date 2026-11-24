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
            default used by every page that doesn't set its own. The same
            $ogDescriptionText also fills the plain <meta name="description">
            search engines use for the result snippet — no page had one at
            all before this, so Google was generating its own (often
            awkward) snippet text from whatever it found on the page.
        --}}
        @php
            $ogImageUrl = $ogImage ?? app(\App\Services\Settings\BusinessProfile::class)->logoUrl();
            $ogDescriptionText = $ogDescription ?? $defaultDescription;
        @endphp
        <meta name="description" content="{{ $ogDescriptionText }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $ogDescriptionText }}">
        @if ($ogImageUrl)
            <meta property="og:image" content="{{ $ogImageUrl }}">
        @endif
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
        <meta name="twitter:description" content="{{ $ogDescriptionText }}">
        @if ($ogImageUrl)
            <meta name="twitter:image" content="{{ $ogImageUrl }}">
        @endif

        @include('partials.brand-head')
        {{--
            Icons + theme color only — this is not the full CLAUDE.md PWA
            requirement (that also needs a service worker for offline menu
            caching, a separate piece of work). Lets a phone add the site
            to its home screen with a real icon in the meantime.
        --}}
        <link rel="manifest" href="{{ route('manifest') }}">


        {{-- echo.js before customer.js: modules run in order, and Alpine components using window.Echo initialise as soon as customer.js starts Alpine. --}}
        @include('partials.reverb-config')
        @include('partials.form-feedback')
        @vite(['resources/css/app.css', ...($realtime ? ['resources/js/echo.js'] : []), 'resources/js/customer.js'])
    </head>
    <body class="font-customer antialiased theme-{{ $theme }} {{ $bodyClass }} text-brand-black min-h-screen flex flex-col">
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
            class="fixed inset-0 z-50 bg-brand-surface flex flex-col items-center justify-center gap-6"
        >
            <x-brand-logo class="h-16 w-auto" text-class="font-bold text-3xl text-brand-on-surface" />
            <svg viewBox="0 0 24 24" fill="none" class="animate-spin w-8 h-8 text-brand-highlight">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
            </svg>
        </div>

        @if ($themePreviewing && $websiteOn)
            {{-- A staff preview of another website look (Website content → Website look): this browser only. --}}
            <div class="bg-brand-surface text-brand-on-surface text-xs sm:text-sm px-4 py-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-center">
                <span>{{ __('Previewing the :theme look. Customers still see the look chosen in Website content.', ['theme' => __(\App\Services\Content\WebsiteThemeService::THEMES[$themePreviewing]['label'])]) }}</span>
                <a href="{{ request()->fullUrlWithQuery(['theme_preview' => 'off']) }}" class="font-semibold underline">{{ __('End preview') }}</a>
            </div>
        @endif

        {{--
            x-data lives here, not on <header> — the drawer below needs to
            sit outside the header so its z-index isn't capped by the
            header's own stacking context (a sticky+z-40 element creates
            one; no z-index on a descendant can ever escape it). See the
            drawer's own comment.
        --}}
        <div x-data="{ open: false }">
        @if ($theme !== 'classic')
            @include('themes.'.$theme.'.header')
        @else
        <header class="bg-brand-surface sticky top-0 z-40">
            <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
                <a href="{{ route('home') }}">
                    <x-brand-logo class="h-11 md:h-14 w-auto max-w-[11rem] md:max-w-[15rem] object-contain" text-class="font-bold text-xl text-brand-on-surface" />
                </a>

                @if ($websiteOn)
                <nav class="hidden md:flex items-center gap-6 text-sm text-brand-on-surface">
                    <a href="{{ route('home') }}" class="hover:text-brand-highlight {{ request()->routeIs('home') ? 'text-brand-highlight font-semibold' : '' }}">{{ __('Home') }}</a>
                    <a href="{{ route('menu.index') }}" class="hover:text-brand-highlight {{ request()->routeIs('menu.index') ? 'text-brand-highlight font-semibold' : '' }}">{{ __('Menu') }}</a>
                    <a href="{{ route('branches.index') }}" class="hover:text-brand-highlight {{ request()->routeIs('branches.index') ? 'text-brand-highlight font-semibold' : '' }}">{{ __('Branches') }}</a>
                    <a href="{{ route('contact') }}" class="hover:text-brand-highlight {{ request()->routeIs('contact') ? 'text-brand-highlight font-semibold' : '' }}">{{ __('Contact us') }}</a>
                    @if ($publishedPages['about'])
                    <a href="{{ route('about') }}" class="hover:text-brand-highlight {{ request()->routeIs('about') ? 'text-brand-highlight font-semibold' : '' }}">{{ __('About us') }}</a>
                    @endif
                    <a href="{{ route('tracking.lookup') }}" class="hover:text-brand-highlight {{ request()->routeIs('tracking.*') ? 'text-brand-highlight font-semibold' : '' }}">{{ __('Track order') }}</a>
                </nav>
                @endif

                @if ($websiteOn)
                <div class="hidden md:flex items-center gap-4">
                    <a href="{{ route('cart.show') }}" class="relative text-brand-on-surface hover:text-brand-highlight" aria-label="{{ __('Cart') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        @if($cartItemCount > 0)
                            <span class="absolute -top-2 -right-3 bg-brand-yellow text-brand-on-primary text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ $cartItemCount }}</span>
                        @endif
                    </a>

                    @auth('customer')
                        <a href="{{ route('customer.account.show') }}" class="text-sm text-brand-on-surface hover:text-brand-highlight {{ request()->routeIs('customer.account.*') ? 'text-brand-highlight font-semibold' : '' }}">{{ __('My account') }}</a>
                        <form method="POST" action="{{ route('customer.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-brand-on-surface hover:text-brand-highlight">{{ __('Log out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('customer.login') }}" class="text-sm text-brand-on-surface hover:text-brand-highlight">{{ __('Login') }}</a>
                        <a href="{{ route('customer.register') }}" class="px-3 py-1.5 bg-brand-yellow text-brand-on-primary text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Sign up') }}</a>
                    @endauth
                </div>
                @endif

                @if ($websiteOn)
                {{--
                    SVG, not a Unicode "&#9776;" glyph — a raw hamburger
                    character renders using whatever system font the OS
                    supplies for that code point, which varies visibly in
                    size/weight/vertical alignment across iOS, Android, and
                    OEM skins. An inline SVG is pixel-identical everywhere,
                    same as the staff/rider dashboards' hamburger button.
                --}}
                <button
                    type="button" class="md:hidden text-brand-on-surface p-2 -me-2 touch-manipulation" @click="open = true; $dispatch('customer-drawer-opened')"
                    aria-label="{{ __('Open menu') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                @endif
            </div>
        </header>
        @endif

            @if ($websiteOn)
            {{--
                Off-canvas drawer — same pattern as the staff/rider
                dashboards (layouts/navigation.blade.php, layouts/
                rider.blade.php: dimmed backdrop, slide-in panel, role
                dialog/aria-modal, md breakpoint), just brand-colored, so
                "open the mobile menu" behaves identically everywhere in
                the app instead of this being the one plain accordion with
                no backdrop that a different breakpoint and a different
                interaction from every other nav in the product.
            --}}
            {{--
                Slides in over 120ms (was 200ms), with will-change so iOS
                Safari composites the panel and backdrop up front instead of
                painting them over the photo-heavy page on the first frame.
                Opening/closing also tells the homepage hero to pause its
                slideshow (customer-drawer-opened/-closed).
            --}}
            {{--
                z-[60], not z-40 — the cookie-consent banner and the
                added-to-cart modal both sit at z-50 (below), and a
                first-time visitor who hasn't dismissed the cookie banner
                yet would otherwise open this drawer and find its own
                Login/Sign up footer hidden behind the banner. An
                open navigation menu is a deliberate user action; it
                should always be the topmost thing on screen.
            --}}
            <div x-show="open" x-cloak x-effect="if (! open) $dispatch('customer-drawer-closed')" class="md:hidden fixed inset-0 z-[60]" role="dialog" aria-modal="true">
                <div
                    class="fixed inset-0 bg-black/50 will-change-[opacity]"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-[120ms]"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="open = false"
                ></div>

                <div
                    class="relative w-64 h-full bg-brand-surface flex flex-col will-change-transform"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-[120ms]"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                >
                    <div class="h-20 flex items-center justify-between px-4 border-b border-brand-on-surface/15 shrink-0">
                        <a href="{{ route('home') }}">
                            <x-brand-logo class="h-10 w-auto max-w-[10rem] object-contain" text-class="font-bold text-lg text-brand-on-surface" />
                        </a>
                        <button type="button" @click="open = false" aria-label="{{ __('Close menu') }}" class="p-2 -me-2 text-brand-on-surface">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                                <path d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-2 text-brand-on-surface text-sm" @click="open = false">
                        @foreach ($customerNav as $link)
                        <a href="{{ $link['url'] }}" class="block hover:text-brand-highlight {{ $link['active'] ? 'text-brand-highlight font-semibold' : '' }}">@if ($link['number'])<span class="text-brand-on-surface/70 text-xs me-1">{{ $link['number'] }}</span>@endif{{ $link['label'] }}</a>
                        @endforeach
                        <a href="{{ route('cart.show') }}" class="flex items-center gap-2 hover:text-brand-highlight">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 shrink-0">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            {{ __('Cart') }} ({{ $cartItemCount }})
                        </a>
                    </nav>

                    <div class="border-t border-brand-on-surface/15 p-4 shrink-0">
                        @auth('customer')
                            <a href="{{ route('customer.account.show') }}" class="block text-sm font-semibold text-brand-on-surface hover:text-brand-highlight mb-2 truncate">{{ __('My account') }} · {{ auth('customer')->user()->name ?? auth('customer')->user()->phone }}</a>
                            <form method="POST" action="{{ route('customer.logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-brand-on-surface/70 hover:text-brand-highlight">{{ __('Log out') }}</button>
                            </form>
                        @else
                            <div class="flex gap-3">
                                <a href="{{ route('customer.login') }}" class="flex-1 text-center px-3 py-2 border border-brand-on-surface/40 text-brand-on-surface text-sm font-semibold rounded-md">{{ __('Login') }}</a>
                                <a href="{{ route('customer.register') }}" class="flex-1 text-center px-3 py-2 bg-brand-yellow text-brand-on-primary text-sm font-semibold rounded-md">{{ __('Sign up') }}</a>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
            @endif

        @isset($fullHero)
            {{ $fullHero }}
        @endisset

        @isset($pageHeader)
            @if ($theme === 'classic')
            <div class="bg-brand-surface py-10 border-t border-brand-highlight">
                <div class="max-w-5xl mx-auto px-4">
                    <h1 class="text-2xl font-bold text-brand-on-surface text-center">{{ $pageHeader }}</h1>
                </div>
            </div>
            @else
            <div class="bg-brand-yellow-light py-12">
                <div class="max-w-5xl mx-auto px-4">
                    <h1 class="font-heading text-3xl sm:text-4xl text-brand-black text-center">{{ $pageHeader }}</h1>
                </div>
            </div>
            @endif
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
                        <a href="{{ route('cart.show') }}" class="w-full px-4 py-2.5 bg-brand-yellow text-brand-on-primary text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">
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

        {{--
            A small floating card rather than a full-width bar: the page
            stays usable underneath (browsing and ordering never wait on a
            choice), and on a phone it covers far less of the screen.
            Bottom-centre on phones, bottom-right from sm up.
        --}}
        @if ($websiteOn)
        <div
            x-data="{ show: {{ $cookieConsentGiven ? 'false' : 'true' }} }"
            x-on:open-cookie-banner.window="show = true"
            x-show="show"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            role="dialog" aria-live="polite" aria-label="{{ __('Cookie preferences') }}"
            class="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:right-4 sm:w-80 z-50 bg-brand-surface border border-brand-highlight rounded-xl shadow-lg p-4 text-sm text-brand-on-surface/70"
        >
            <p>
                {{ __('We use one first-party cookie to see how our site is used. The site works either way.') }}
                @if ($publishedPages['cookies'])
                    <a href="{{ route('policy.cookies') }}" class="underline hover:text-brand-highlight">{{ __('Learn more') }}</a>
                @endif
            </p>
            <div class="flex gap-2 mt-3">
                <form method="POST" action="{{ route('cookie-consent.update') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="choice" value="decline">
                    <button type="submit" class="w-full px-3 py-1.5 border border-brand-on-surface/40 text-brand-on-surface text-sm font-semibold rounded-md hover:bg-brand-on-surface/10">{{ __('Decline') }}</button>
                </form>
                <form method="POST" action="{{ route('cookie-consent.update') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="choice" value="accept">
                    <button type="submit" class="w-full px-3 py-1.5 bg-brand-yellow text-brand-on-primary text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Accept') }}</button>
                </form>
            </div>
        </div>
        @endif

        <footer class="mt-12 bg-brand-surface border-t border-brand-highlight">
            <div class="max-w-5xl mx-auto px-4 py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-10 text-sm text-brand-on-surface/70">
                <div>
                    <x-brand-logo class="h-12 w-auto max-w-[12rem] object-contain mb-4" text-class="block font-bold text-lg text-brand-on-surface mb-4" />
                    @if ($footerTagline)
                        <p class="text-brand-on-surface/70">{{ $footerTagline }}</p>
                    @endif
                </div>

                @if ($websiteOn)
                <div>
                    <p class="font-semibold text-brand-highlight uppercase text-xs tracking-wide mb-4">{{ __('Quick links') }}</p>
                    <nav class="space-y-2">
                        <a href="{{ route('home') }}" class="block hover:text-brand-highlight transition">{{ __('Home') }}</a>
                        <a href="{{ route('menu.index') }}" class="block hover:text-brand-highlight transition">{{ __('Menu') }}</a>
                        <a href="{{ route('branches.index') }}" class="block hover:text-brand-highlight transition">{{ __('Branches') }}</a>
                        <a href="{{ route('contact') }}" class="block hover:text-brand-highlight transition">{{ __('Contact us') }}</a>
                        @if ($publishedPages['about'])
                        <a href="{{ route('about') }}" class="block hover:text-brand-highlight transition">{{ __('About us') }}</a>
                        @endif
                        <a href="{{ route('tracking.lookup') }}" class="block hover:text-brand-highlight transition">{{ __('Track order') }}</a>
                        @if ($publishedPages['faq'])
                        <a href="{{ route('faq') }}" class="block hover:text-brand-highlight transition">{{ __('FAQ') }}</a>
                        @endif
                        @module('reviews')
                            <a href="{{ route('reviews.index') }}" class="block hover:text-brand-highlight transition">{{ __('Reviews') }}</a>
                        @endmodule
                    </nav>
                </div>

                <div>
                    <p class="font-semibold text-brand-highlight uppercase text-xs tracking-wide mb-4">{{ __('Policy') }}</p>
                    <nav class="space-y-2">
                        @if ($publishedPages['terms'])
                        <a href="{{ route('policy.terms') }}" class="block hover:text-brand-highlight transition">{{ __('Terms & Conditions') }}</a>
                        @endif
                        @if ($publishedPages['refunds'])
                        <a href="{{ route('policy.refunds') }}" class="block hover:text-brand-highlight transition">{{ __('Refund Policy') }}</a>
                        @endif
                        @if ($publishedPages['privacy'])
                        <a href="{{ route('policy.privacy') }}" class="block hover:text-brand-highlight transition">{{ __('Privacy Policy') }}</a>
                        @endif
                        @if ($publishedPages['cookies'])
                        <a href="{{ route('policy.cookies') }}" class="block hover:text-brand-highlight transition">{{ __('Cookie Policy') }}</a>
                        @endif
                        <button type="button" @click="$dispatch('open-cookie-banner')" class="block hover:text-brand-highlight transition text-left">{{ __('Cookie preferences') }}</button>
                    </nav>
                </div>
                @endif

                @if ($footerHasSocialLinks)
                    <div>
                        <p class="font-semibold text-brand-highlight uppercase text-xs tracking-wide mb-4">{{ __('Follow us') }}</p>
                        <x-social-links icon-class="text-brand-on-surface/70" />
                    </div>
                @endif

                @if ($footerBranches !== [])
                    <div>
                        <p class="font-semibold text-brand-highlight uppercase text-xs tracking-wide mb-4">{{ __('Call us') }}</p>
                        @foreach ($footerBranches as $branch)
                            <a href="tel:{{ $branch['tel'] }}" class="block hover:text-brand-highlight transition mb-2"><span class="font-bold text-brand-on-surface">{{ $branch['name'] }}</span><br>{{ $branch['display'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="max-w-5xl mx-auto px-4 py-4 border-t border-brand-on-surface/15 text-sm text-brand-on-surface/70 text-center">
                &copy; {{ now()->year }} {{ config('app.name') }}
                &middot;
                {{ __('Designed by') }} <a href="tel:+233263702929" class="hover:text-brand-highlight transition">Solusys Digital: 026 370 2929</a>
            </div>
        </footer>

        @if ($pageViewId = request()->attributes->get('page_view_id'))
            {{--
                Times how long this page stayed open, for the Privacy
                Policy's "pages you view and how long you spend on them".
                sendBeacon fires on the way out (pagehide covers back/
                forward-cache navigations that visibilitychange alone can
                miss) and never blocks the page from actually unloading —
                zero impact on the interactive-time budget. The CSRF token
                travels in the body rather than a header because sendBeacon
                can't set custom headers; Laravel's VerifyCsrfToken already
                falls back to the _token input field for exactly this case.
            --}}
            <script>
                (function () {
                    var pageViewId = {{ (int) $pageViewId }};
                    var startedAt = performance.now();
                    var sent = false;

                    function reportDuration() {
                        if (sent) return;
                        sent = true;

                        var seconds = Math.round((performance.now() - startedAt) / 1000);
                        var body = new Blob(
                            ['_token=' + encodeURIComponent('{{ csrf_token() }}') + '&duration_seconds=' + seconds],
                            { type: 'application/x-www-form-urlencoded' }
                        );

                        navigator.sendBeacon('/page-views/' + pageViewId + '/duration', body);
                    }

                    document.addEventListener('visibilitychange', function () {
                        if (document.visibilityState === 'hidden') reportDuration();
                    });
                    window.addEventListener('pagehide', reportDuration);
                })();
            </script>
        @endif
    </body>
</html>
