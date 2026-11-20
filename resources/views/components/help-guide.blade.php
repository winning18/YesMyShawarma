@props(['title', 'summary' => null])
{{--
    A Help guide's frame (step 10): title, Back and Print buttons, and print
    styles that leave out the app's navigation so the guide prints clean.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard.help.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('All guides') }}</a>
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Print') }}</button>
            </div>
        </div>
    </x-slot>

    <style>
        @media print {
            nav, header, aside, [data-print-hide] { display: none !important; }
            body { background: #fff !important; }
            .help-guide { box-shadow: none !important; padding: 0 !important; }
            .help-guide section { break-inside: avoid; }
        }
        .help-guide h3 { font-weight: 600; font-size: 1.05rem; color: #1f2937; margin-top: 1.75rem; margin-bottom: .5rem; }
        .help-guide h3:first-child { margin-top: 0; }
        .help-guide p { color: #4b5563; font-size: .9rem; margin-bottom: .5rem; }
        .help-guide ol { list-style: decimal; padding-left: 1.25rem; color: #374151; font-size: .9rem; }
        .help-guide ul { list-style: disc; padding-left: 1.25rem; color: #374151; font-size: .9rem; }
        .help-guide li { margin-bottom: .35rem; }
        .help-guide .tip { background: #f9fafb; border-left: 3px solid #9ca3af; padding: .5rem .75rem; font-size: .85rem; color: #4b5563; margin: .75rem 0; }
        .help-guide b { color: #111827; }
    </style>

    <div class="max-w-3xl mx-auto py-8 px-4">
        <article class="help-guide bg-white shadow rounded-lg p-6 sm:p-8">
            <p class="hidden print:block text-lg font-semibold text-gray-900">{{ $title }}</p>
            @if ($summary)
                <p class="!text-gray-500 !mb-6">{{ $summary }}</p>
            @endif
            {{ $slot }}
        </article>
    </div>
</x-app-layout>
