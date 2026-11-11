{{--
    Profit report PDF (ProfitReportExporter::pdf()), in the shared report
    design: one row per period, totals, then spend by kind.
--}}
@php
    $amount = fn (int $pesewas) => number_format($pesewas / 100, 2);
    $totals = $report['totals'];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    @include('dashboard.reports.pdf._styles')
</head>
<body>
    @include('dashboard.reports.pdf._letterhead')

    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('Period') }}</th>
                <th class="num">{{ __('Sales') }} (ghs)</th>
                <th class="num">{{ __('Refunds') }} (ghs)</th>
                <th class="num">{{ __('Purchases & expenses') }} (ghs)</th>
                <th class="num">{{ __('Profit') }} (ghs)</th>
                <th class="num">{{ __('Bolt Food, before commission') }} (ghs)</th>
                <th class="num">{{ __('Profit incl. Bolt Food') }} (ghs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['periods'] as $period)
                <tr>
                    <td>{{ $period['label'] }}</td>
                    <td class="num">{{ $amount($period['sales']) }}</td>
                    <td class="num">{{ $amount($period['refunds']) }}</td>
                    <td class="num">{{ $amount($period['purchases']) }}</td>
                    <td class="num">{{ $amount($period['profit']) }}</td>
                    <td class="num">{{ $amount($period['bolt_food']) }}</td>
                    <td class="num">{{ $amount($period['profit_with_bolt']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>TOTAL</td>
                <td class="num">{{ $amount($totals['sales']) }}</td>
                <td class="num">{{ $amount($totals['refunds']) }}</td>
                <td class="num">{{ $amount($totals['purchases']) }}</td>
                <td class="num">{{ $amount($totals['profit']) }}</td>
                <td class="num">{{ $amount($totals['bolt_food']) }}</td>
                <td class="num">{{ $amount($totals['profit_with_bolt']) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="two-col" style="margin-top: 18px; page-break-inside: avoid;">
        <tr>
            <td style="width: 50%; padding-right: 12px;">
                <h2 style="margin-top: 0;">{{ __('Purchases by kind') }}</h2>
                <table class="summary">
                    <tr><th style="text-align: left;">{{ __('Kind') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Total') }} (ghs)</th></tr>
                    @forelse ($report['by_kind'] as $kind)
                        <tr>
                            <td>{{ $kind['kind'] }} ({{ $kind['count'] }})</td>
                            <td class="num">{{ $kind['quantity'] !== null ? rtrim(rtrim(number_format($kind['quantity'], 2), '0'), '.').' '.$kind['unit'] : '' }}</td>
                            <td class="num">{{ $amount($kind['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">{{ __('No approved purchases in this period.') }}</td></tr>
                    @endforelse
                </table>
            </td>
            <td>
                <h2 style="margin-top: 0;">{{ __('Totals') }}</h2>
                <table class="summary">
                    <tr><th></th><th>{{ __('Total') }} (ghs)</th></tr>
                    <tr><td>{{ __('Sales') }}</td><td class="num">{{ $amount($totals['sales']) }}</td></tr>
                    <tr><td>{{ __('Refunds') }}</td><td class="num">-{{ $amount($totals['refunds']) }}</td></tr>
                    <tr><td>{{ __('Purchases & expenses') }}</td><td class="num">-{{ $amount($totals['purchases']) }}</td></tr>
                    <tr class="net"><td>{{ __('Profit') }}</td><td class="num">{{ $amount($totals['profit']) }}</td></tr>
                    <tr class="memo"><td>{{ __('Bolt Food sales, before Bolt\'s commission') }}</td><td class="num">{{ $amount($totals['bolt_food']) }}</td></tr>
                    <tr class="memo"><td>{{ __('Profit incl. Bolt Food') }}</td><td class="num">{{ $amount($totals['profit_with_bolt']) }}</td></tr>
                    @if ($report['awaiting']['count'] > 0)
                        <tr class="memo"><td>{{ __('Not yet approved, not counted') }} ({{ $report['awaiting']['count'] }})</td><td class="num">{{ $amount($report['awaiting']['total']) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
