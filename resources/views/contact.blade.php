<x-customer-layout title="Contact us · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-6">{{ __('Contact us') }}</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
        <div>
            <h2 class="font-semibold mb-3">{{ __('Call or WhatsApp') }}</h2>
            <ul class="space-y-2 text-sm">
                <li>
                    <span class="text-brand-gray-500">{{ __('Ga Odumase (main):') }}</span>
                    <a href="tel:+233243635265" class="underline">0243635265</a>
                </li>
                <li>
                    <span class="text-brand-gray-500">{{ __('Pokuase Y-Junction:') }}</span>
                    <a href="tel:+233531907747" class="underline">0531907747</a>
                </li>
            </ul>

            <h2 class="font-semibold mt-6 mb-3">{{ __('Email') }}</h2>
            <ul class="space-y-2 text-sm">
                <li><a href="mailto:yesmyshawarma@gmail.com" class="underline">yesmyshawarma@gmail.com</a></li>
                <li><a href="mailto:yesmygrill@gmail.com" class="underline">yesmygrill@gmail.com</a></li>
            </ul>
        </div>

        <div>
            <h2 class="font-semibold mb-3">{{ __('Our branches') }}</h2>
            <ul class="space-y-4 text-sm">
                @foreach ($branches as $branch)
                    <li>
                        <p class="font-medium">{{ $branch->name }}</p>
                        <p class="text-brand-gray-500">{{ $branch->address }}</p>
                    </li>
                @endforeach
            </ul>

            <h2 class="font-semibold mt-6 mb-3">{{ __('Follow us') }}</h2>
            <ul class="space-y-2 text-sm">
                <li><a href="https://www.instagram.com/yesmygrill_shawarma" target="_blank" rel="noopener" class="underline">Instagram</a></li>
                <li><a href="https://www.facebook.com/share/18ving6kE3/" target="_blank" rel="noopener" class="underline">Facebook</a></li>
                <li><a href="https://www.tiktok.com/@ymgrillnshawarma" target="_blank" rel="noopener" class="underline">TikTok</a></li>
            </ul>
        </div>
    </div>
</x-customer-layout>
