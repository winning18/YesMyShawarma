@props(['bag' => 'default'])
{{--
    Every mistake from the last submit, at the top of the form (owner,
    2026-11-24). resources/js/form-feedback.js still outlines each field in
    red with its message; this replaces the summary it would otherwise add.
--}}
@php($messages = isset($errors) ? $errors->getBag($bag)->all() : [])
@if ($messages !== [])
    <div {{ $attributes->merge(['class' => 'xpos-error-summary']) }} role="alert" tabindex="-1" data-error-summary>
        <p class="xpos-error-summary-title">{{ count($messages) === 1 ? __('1 thing needs fixing before this can be saved:') : __(':count things need fixing before this can be saved:', ['count' => count($messages)]) }}</p>
        <ul>
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
