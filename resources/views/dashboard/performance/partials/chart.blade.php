@php
    // $divisor/$unit default to the original pesewas->GHS money chart —
    // every existing caller (Sales tab) passes neither, so behaviour there
    // is unchanged. Traffic passes divisor=1/unit='' to plot plain counts
    // through the exact same axis/scaling/gridline logic instead of
    // duplicating this file just to drop the /100 conversion.
    $divisor = $divisor ?? 100;
    $unit = $unit ?? 'GHS';
    $ariaLabel = $ariaLabel ?? __('Sales over time, current period vs previous period');

    $width = 800;
    $height = 260;
    $padLeft = 60;
    $padBottom = 30;
    $padTop = 10;
    $chartW = $width - $padLeft;
    $chartH = $height - $padBottom - $padTop;
    $n = count($current);

    $maxRaw = max(1, max(array_merge($current, $previous, [1])));
    $maxScaled = $maxRaw / $divisor;
    // Money always rounds up to a nice round 100 (GHS amounts are
    // comfortably above that). A plain count (divisor=1, e.g. visits/day)
    // can be single digits, so rounding to the same fixed 100 would flatten
    // a real small-number chart against the bottom of the axis — round up
    // to a step sized to the data's own magnitude instead (5 -> step 1,
    // 47 -> step 10, 230 -> step 100).
    $roundTo = $divisor === 1 ? max(1, (int) (10 ** floor(log10(max(1, $maxScaled))))) : 100;
    $niceMax = max($roundTo, (int) (ceil($maxScaled / $roundTo) * $roundTo));

    $toPoint = function (int $index, int $value) use ($n, $chartW, $chartH, $padLeft, $padTop, $niceMax, $divisor) {
        $x = $n <= 1 ? $padLeft + $chartW / 2 : $padLeft + ($index / ($n - 1)) * $chartW;
        $y = $padTop + $chartH * (1 - ($value / $divisor) / $niceMax);

        return round($x, 1).','.round($y, 1);
    };

    $currentPoints = collect($current)->map(fn ($v, $i) => $toPoint($i, $v))->values();
    $previousPoints = collect($previous)->map(fn ($v, $i) => $toPoint($i, $v))->values();

    $bottomY = $padTop + $chartH;
    $areaPath = 'M'.$currentPoints->first().' L'.$currentPoints->implode(' ').' L'.explode(',', $currentPoints->last())[0].','.$bottomY.' L'.explode(',', $currentPoints->first())[0].','.$bottomY.' Z';

    // Thin the x-axis labels when there are too many days to fit legibly —
    // at most ~8 labels regardless of range length.
    $labelStep = max(1, (int) ceil($n / 8));
@endphp

<div class="bg-white shadow rounded-lg p-4 overflow-x-auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full min-w-[600px]" style="height: {{ $height }}px" preserveAspectRatio="none" role="img" aria-label="{{ $ariaLabel }}">
        <defs>
            <linearGradient id="performanceAreaFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="rgb(16 185 129 / 0.25)" />
                <stop offset="100%" stop-color="rgb(16 185 129 / 0)" />
            </linearGradient>
        </defs>

        {{-- Gridlines + Y axis labels --}}
        @for ($i = 0; $i <= 4; $i++)
            @php
                $gy = $padTop + $chartH * (1 - $i / 4);
                $gv = (int) ($niceMax * $i / 4);
            @endphp
            <line x1="{{ $padLeft }}" y1="{{ $gy }}" x2="{{ $width }}" y2="{{ $gy }}" stroke="#f0f0f0" stroke-width="1" />
            <text x="{{ $padLeft - 8 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="11" fill="#9ca3af">{{ trim($unit.' '.number_format($gv)) }}</text>
        @endfor

        {{-- Previous period (dotted) --}}
        <polyline points="{{ $previousPoints->implode(' ') }}" fill="none" stroke="#10b981" stroke-width="2" stroke-dasharray="4 4" opacity="0.6" />

        {{-- Current period (solid, filled) --}}
        <path d="{{ $areaPath }}" fill="url(#performanceAreaFill)" />
        <polyline points="{{ $currentPoints->implode(' ') }}" fill="none" stroke="#10b981" stroke-width="2.5" />

        {{-- X axis labels --}}
        @foreach ($labels as $i => $label)
            @if ($i % $labelStep === 0)
                <text x="{{ explode(',', $currentPoints[$i])[0] }}" y="{{ $height - 8 }}" text-anchor="middle" font-size="11" fill="#9ca3af">{{ $label }}</text>
            @endif
        @endforeach
    </svg>
</div>
