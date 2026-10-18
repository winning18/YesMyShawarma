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
    <style>
        @font-face { font-family: 'Figtree'; font-weight: 400; src: url('{{ $fontDir }}/figtree-400.ttf') format('truetype'); }
        @font-face { font-family: 'Figtree'; font-weight: 600; src: url('{{ $fontDir }}/figtree-600.ttf') format('truetype'); }
        @font-face { font-family: 'Figtree'; font-weight: 700; src: url('{{ $fontDir }}/figtree-700.ttf') format('truetype'); }

        @page { margin: 28px 32px 36px; }
        body { font-family: 'Figtree', sans-serif; color: #111; font-size: 9px; }
        .rule { border-top: 2px solid #9a0200; margin: 10px 0 14px; }
        .header td { vertical-align: top; }
        .logo { height: 46px; }
        h1 { font-size: 20px; font-weight: 400; margin: 14px 0 0; }
        .muted { color: #555; }
        .strong { font-weight: 700; }
        .period { font-weight: 700; font-size: 10px; margin: 0 0 8px; }
        h2 { font-size: 10px; font-weight: 700; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.04em; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { font-size: 8px; font-weight: 700; text-align: left; padding: 4px 5px; border-bottom: 1.5px solid #111; vertical-align: bottom; }
        table.grid td { padding: 4px 5px; }
        table.grid .num { text-align: right; white-space: nowrap; }
        table.grid th.caption { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: none; padding: 0 0 6px; }
        table.grid tr.total td { background: #eeeeee; font-weight: 700; }
        table.grid tr.gap td { padding: 5px; }

        table.summary { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.summary th { background: #eeeeee; text-align: right; padding: 5px 6px; font-weight: 700; }
        table.summary td { padding: 5px 6px; }
        table.summary td.num { text-align: right; width: 120px; }
        table.summary tr.net td { background: #eeeeee; font-weight: 700; border-top: 2px solid #9a0200; color: #9a0200; font-size: 11px; }
        table.summary tr.memo td { color: #555; padding-top: 3px; padding-bottom: 3px; }

        .two-col { width: 100%; }
        .two-col td { vertical-align: top; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #888; }
    </style>
</head>
<body>
    <div class="footer">{{ config('app.name') }} · {{ $report['title'] }} · {{ __('Generated') }} {{ now('Africa/Accra')->format('Y-m-d H:i') }}</div>

    <table class="header" style="width: 100%;">
        <tr>
            <td>
                @if (is_file($logoPath))
                    <img src="{{ $logoPath }}" class="logo" alt="{{ config('app.name') }}">
                @else
                    <span class="strong" style="font-size: 18px;">{{ config('app.name') }}</span>
                @endif
                <h1>{{ $report['title'] }}</h1>
            </td>
            <td style="text-align: right; line-height: 1.6;">
                <span class="strong" style="font-size: 11px;">{{ config('app.name') }}</span><br>
                @if ($report['branch'])
                    {{ $report['branch']->name }}{{ $report['general'] ? ' ('.__('main branch').')' : '' }}<br>
                    <span class="muted">{{ $address }}</span><br>
                    <span class="muted">{{ $report['branch']->phone }}</span><br>
                @endif
                @if ($report['general'])
                    <span class="muted">{{ __('All branches') }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <p class="period">{{ __('Period of report') }}: {{ $report['from']->toDateString() }} - {{ $report['to']->toDateString() }}</p>

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
