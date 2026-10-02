@props(['disabled' => false])

@php
    // Highlights a field that failed Laravel validation on the last submit
    // (a server round-trip, so :user-invalid in app.css can't catch it —
    // that's a fresh page load, nothing's been interacted with yet on it).
    // Only works for a plain field name ("email"), not a bracketed array
    // one ("expenses[0][amount]") — $errors keys those with dots instead.
    $name = $attributes->get('name');
    $hasError = $name && $errors->has($name);
@endphp

<input
    @disabled($disabled)
    {{ $attributes->merge(['class' => $hasError
        ? 'border-red-500 ring-1 ring-red-500 focus:border-red-500 focus:ring-red-500 rounded-md shadow-sm'
        : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm']) }}
>
