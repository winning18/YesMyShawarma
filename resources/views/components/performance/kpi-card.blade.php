@props(['label', 'value', 'changePct' => null, 'highlight' => false])

<div {{ $attributes->merge(['class' => 'bg-white rounded-lg p-4 '.($highlight ? 'ring-2 ring-green-500' : 'shadow')]) }}>
    <p class="text-xs text-gray-500">{{ $label }}</p>
    <p class="text-xl font-semibold text-gray-800 flex items-baseline gap-2 flex-wrap">
        {{ $value }}
        @if ($changePct !== null)
            <span class="text-xs font-medium {{ $changePct >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $changePct >= 0 ? '↑' : '↓' }} {{ number_format(abs($changePct), 0) }}%
            </span>
        @endif
    </p>
</div>
