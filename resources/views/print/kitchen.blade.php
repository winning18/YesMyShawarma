<x-print-page :title="$data['reference']" :width="$width">
    {{-- The short code the kitchen calls out, then the full reference. --}}
    <p class="center huge">{{ \Illuminate\Support\Str::afterLast($data['reference'], '-') }}</p>
    <p class="center muted">{{ $data['reference'] }}</p>
    @if ($data['table'] ?? null)
        <p class="center huge">{{ __('TABLE :table', ['table' => $data['table']]) }}</p>
    @else
        <p class="center bold">{{ $data['fulfilment'] === 'delivery' ? __('DELIVERY') : __('PICKUP') }}@if ($data['counter']) · {{ $data['counter'] }}@endif</p>
    @endif
    <p class="center">{{ $data['placed_at']?->format('H:i') }}@if ($data['scheduled_for']) · {{ __('for :time', ['time' => $data['scheduled_for']->format('H:i')]) }}@endif</p>
    <div class="rule"></div>
    @foreach ($data['lines'] as $line)
        <p class="big">{{ $line['quantity'] }}× {{ $line['name'] }}</p>
        @foreach ($line['options'] as $option)
            <p class="opt bold">+ {{ $option }}</p>
        @endforeach
        @if ($line['notes'])<p class="opt">{{ __('Note:') }} {{ $line['notes'] }}</p>@endif
    @endforeach
    @if ($data['instructions'])
        <div class="rule"></div>
        <p class="bold">{{ __('Instructions:') }} {{ $data['instructions'] }}</p>
    @endif
</x-print-page>
