{{--
    Shared by Order History, Detailed Reports, the Sales tab, Invoices
    and sales, and Weekly report — a Litepicker calendar behind a single
    trigger button, replacing each page's own "range" <select>/date
    <input> + custom from/to inputs.

    Named presets (Today, Last 7 days, …) are NOT driven through this
    component's JS at all: each caller builds them as plain server-
    rendered <a href> links (passed in via $presetLinks) carrying the
    whole current query string, and they render inside Litepicker's own
    `footerHTML` slot so they sit visually inside the same popover as the
    Bolt-style reference design. A fixed href can't submit the wrong or
    an empty date — the exact reasoning that already moved the Sales
    tab's Today/Yesterday off an Alpine dropdown onto plain links.

    The calendar itself is the only path that needs JS, for the one case
    that genuinely needs a widget: picking an arbitrary custom range (or,
    with `single`, one date — Invoices/Weekly only ever snap a single
    picked date to its containing week server-side, same as their
    previous plain `<input type="date">`). Its 'selected' callback is a
    direct, synchronous write into the hidden field(s) followed by a
    real form submit — no x-model/x-show reactivity involved, so it
    carries none of the timing risk that bug class had.
--}}
@props([
    'from' => null,
    'to' => null,
    'single' => false,
    'fieldName' => 'from',
    'maxDate' => null,
    'label' => null,
    'presetLinks' => [],
    'clearUrl' => null,
])

@php
    $maxDate ??= \Illuminate\Support\Carbon::now('Africa/Accra')->toDateString();
    $displayLabel = $label ?? ($single
        ? ($from ?? __('Pick a date'))
        : (($from && $to) ? "{$from} → {$to}" : __('Any time')));

    $presetHtml = collect($presetLinks)->map(function ($link) {
        $classes = ($link['active'] ?? false)
            ? 'bg-gray-800 text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200';

        return '<a href="'.e($link['url']).'" class="block px-3 py-1.5 rounded-md text-xs font-semibold text-center '.$classes.'">'.e($link['label']).'</a>';
    })->implode('');

    if ($clearUrl) {
        $presetHtml .= '<a href="'.e($clearUrl).'" class="block px-3 py-1.5 rounded-md text-xs font-semibold text-center col-span-2 text-gray-500 hover:text-gray-700 hover:underline">'.e(__('Clear')).'</a>';
    }

    // Litepicker's own renderFooter() unconditionally does
    // t.querySelector('.preview-date-range')/('.button-apply') on
    // whatever footerHTML it's given — including a fully custom one —
    // and calls .innerHTML=/.setAttribute('disabled', '') on the
    // result. Supplying a custom footerHTML with neither element
    // present (ours never did) makes that throw a TypeError the moment
    // a day is picked, since datePicked.length becomes 1 there,
    // aborting Litepicker's whole click handler before it ever reaches
    // the 'preselect'/'selected' emits our own JS listens for — the
    // picker silently stopped doing anything, on every page, the
    // instant you clicked a day. These two hidden elements exist
    // purely to satisfy that internal lookup; they're never shown.
    $footerHtml = ($presetHtml !== '' ? '<div class="p-2 grid grid-cols-2 gap-1 border-t border-gray-100">'.$presetHtml.'</div>' : '')
        .'<span class="preview-date-range" style="display:none"></span>'
        .'<button type="button" class="button-apply" style="display:none" tabindex="-1" aria-hidden="true"></button>';
@endphp

<div x-data="dateRangePicker(@js(['single' => $single, 'from' => $from, 'to' => $to, 'maxDate' => $maxDate, 'footerHtml' => $footerHtml]))" {{ $attributes }}>
    @if ($single)
        <input type="hidden" name="{{ $fieldName }}" x-ref="fromField" value="{{ $from }}">
    @else
        <input type="hidden" name="from" x-ref="fromField" value="{{ $from }}">
        <input type="hidden" name="to" x-ref="toField" value="{{ $to }}">
    @endif

    {{--
        Litepicker's click handler decides whether to open using strict
        `event.target === element` (dist/litepicker.umd.js's shouldShown())
        — not .closest()/.contains(). A click landing on this button's own
        icon or label text makes event.target that child node, not the
        button, so the picker silently never opens. pointer-events-none on
        every child forces the browser to always report the button itself
        as the target, regardless of where inside it gets clicked.
    --}}
    <button
        type="button"
        x-ref="trigger"
        class="flex items-center gap-2 px-3 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 w-full sm:w-auto"
    >
        <svg class="w-4 h-4 text-gray-400 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span class="pointer-events-none">{{ $displayLabel }}</span>
    </button>
</div>
