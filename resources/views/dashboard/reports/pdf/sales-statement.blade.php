{{--
    Sales / Bolt Food statement PDF (SalesStatementExporter::pdf()),
    laid out after Bolt Food's partner sales report. DomPDF: tables and
    floats only, no flexbox/grid. Figtree is loaded from local TTF files
    so the PDF matches the dashboard's typeface; amounts are written as
    "GHS"/"(ghs)" rather than ₵, which Figtree has no glyph for.
--}}
@php
    $columns = $report['columns'];
    $moneyIndex = collect($columns)->search(fn ($column) => $column['money']);
    $cell = fn (int $pesewas) => $pesewas === 0 ? '-' : number_format($pesewas / 100, 2);
    $amount = fn (int $pesewas) => number_format($pesewas / 100, 2);
    $isSales = $report['type'] === \App\Services\Reports\SalesStatementService::TYPE_SALES;
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
                @foreach ($columns as $column)
                    <th class="{{ $column['money'] ? 'num' : '' }}">{{ $column['label'] }}{{ $column['money'] ? ' (ghs)' : '' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['days'] as $day)
                @foreach ($day['rows'] as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ $column['money'] ? 'num' : '' }}">{{ $column['money'] ? $cell($row[$column['key']]) : $row[$column['key']] }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total">
                    @foreach ($columns as $index => $column)
                        <td class="{{ $column['money'] ? 'num' : '' }}">
                            @if ($column['money'])
                                {{ $cell($day['totals'][$column['key']]) }}
                            @elseif ($column['key'] === 'date')
                                {{ $day['date'] }}
                            @elseif ($index === $moneyIndex - 1)
                                TOTAL
                            @endif
                        </td>
                    @endforeach
                </tr>
                @unless ($loop->last)
                    <tr class="gap"><td colspan="{{ count($columns) }}"></td></tr>
                @endunless
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted" style="padding: 14px 5px;">{{ __('No orders in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($isSales)
        <table class="grid" style="margin-top: 18px;">
            <thead>
                <tr><th colspan="{{ $report['general'] ? 5 : 4 }}" class="caption">{{ __('Expenses') }}</th></tr>
                <tr>
                    <th style="width: 70px;">{{ __('Date') }}</th>
                    <th style="width: 40px;">{{ __('Time') }}</th>
                    @if ($report['general'])
                        <th style="width: 110px;">{{ __('Branch') }}</th>
                    @endif
                    <th>{{ __('Description') }}</th>
                    <th class="num">{{ __('Amount') }} (ghs)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['expenses'] as $expense)
                    <tr>
                        <td>{{ $expense['date'] }}</td>
                        <td>{{ $expense['time'] }}</td>
                        @if ($report['general'])
                            <td>{{ $expense['branch'] }}</td>
                        @endif
                        <td>{{ $expense['description'] }}</td>
                        <td class="num">{{ $amount($expense['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $report['general'] ? 5 : 4 }}" class="muted">{{ __('No expenses recorded in this period.') }}</td></tr>
                @endforelse
                <tr class="total">
                    <td colspan="{{ $report['general'] ? 4 : 3 }}">TOTAL</td>
                    <td class="num">{{ $amount($report['expenses_total']) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <table class="two-col" style="margin-top: 18px; page-break-inside: avoid;">
        <tr>
            <td style="width: 34%; padding-right: 24px;">
                <h2 style="margin-top: 0;">{{ __('Orders not counted in sales') }}</h2>
                <table class="summary">
                    @foreach ($report['excluded_counts'] as $status => $count)
                        <tr><td>{{ $status }}</td><td class="num" style="width: 50px;">{{ $count }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td>
                <h2 style="margin-top: 0;">{{ $isSales ? __('Totals') : __('Bolt Food totals') }}</h2>
                <table class="summary">
                    <tr><th></th><th>{{ __('Total') }} (ghs)</th></tr>
                    @foreach ($report['summary_lines'] as $line)
                        <tr class="{{ $line['emphasis'] ? 'net' : '' }}">
                            <td>{{ $line['label'] }}</td>
                            <td class="num">{{ $amount($line['amount']) }}</td>
                        </tr>
                    @endforeach
                    @foreach ($report['memo_lines'] as $line)
                        <tr class="memo"><td>{{ $line['label'] }}</td><td class="num">{{ $line['value'] }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
