@props(['textClass' => 'font-bold text-lg'])

@if ($url)
    <img src="{{ $url }}" alt="{{ $name }}" {{ $attributes }}>
@else
    <span {{ $attributes->merge(['class' => $textClass]) }}>{{ $name }}</span>
@endif
