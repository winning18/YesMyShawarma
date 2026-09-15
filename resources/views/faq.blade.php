<x-customer-layout title="FAQ · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Frequently asked questions') }}</x-slot>

    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    {{--
        Native <details>/<summary> rather than an Alpine accordion: no JS
        needed to render or expand it, which matters on the throttled-3G
        budget this site is built to (see CLAUDE.md's performance budget).
        Tailwind's group-open: variant (supported since 3.1, installed
        here) handles the plus-to-cross rotation. $items comes from
        FaqController::ITEMS — the same list the FAQPage schema above is
        built from, so the two can never say different things.
    --}}
    <div class="max-w-3xl mx-auto divide-y divide-brand-gray-100">
        @foreach ($items as $item)
            <details class="group py-4">
                <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                    {{ $item['question'] }}
                    <span class="shrink-0 text-brand-yellow-dark font-bold transition-transform group-open:rotate-45">+</span>
                </summary>
                <p class="mt-2 text-sm text-brand-gray-500">{{ $item['answer'] }}</p>
            </details>
        @endforeach
    </div>
</x-customer-layout>
