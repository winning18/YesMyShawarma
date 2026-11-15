{{--
    In every layout's <head>: the business's icon (when one is uploaded)
    and its brand colours as CSS variables (BusinessProfile).
    tailwind.config.js reads the colours through these; app.css holds the
    defaults for any page rendered without this partial.
--}}
@if ($brandIconUrl)
    <link rel="icon" type="image/png" href="{{ $brandIconUrl }}">
    <link rel="apple-touch-icon" href="{{ $brandIconUrl }}">
@endif
<style>:root{@foreach ($brandCssVariables as $name => $channels){{ $name }}:{{ $channels }};@endforeach}</style>
