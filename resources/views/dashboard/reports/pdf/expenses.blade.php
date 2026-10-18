{{--
    Expenses report PDF (ExpenseReportExporter::pdf()), in the same
    design as the sales statement via the shared _styles/_letterhead
    partials: entries grouped by day with a TOTAL row per day, then the
    overall totals and any active filters.
--}}
@php
    $amount = fn (int $pesewas) => number_format($pesewas / 100, 2);
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

    @if ($report['filters'] !== [])
        <p class="muted" style="margin: -4px 0 8px;">{{ __('Filtered by') }}: {{ implode(' · ', $report['filters']) }}</p>
    @endif

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
                @foreach ($day['rows'] as $expense)
                    <tr>
                        @foreach ($columns as $column)
                            <td class="{{ $column['money'] ? 'num' : '' }}">{{ $column['money'] ? $amount($expense[$column['key']]) : $expense[$column['key']] }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="total">
                    <td>{{ $day['date'] }}</td>
                    <td colspan="{{ count($columns) - 3 }}"></td>
                    <td>TOTAL</td>
                    <td class="num">{{ $amount($day['total']) }}</td>
                </tr>
                @unless ($loop->last)
                    <tr class="gap"><td colspan="{{ count($columns) }}"></td></tr>
                @endunless
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted" style="padding: 14px 5px;">{{ __('No expenses in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="two-col" style="margin-top: 18px; page-break-inside: avoid;">
        <tr>
            <td style="width: 50%;"></td>
            <td>
                <h2 style="margin-top: 0;">{{ __('Totals') }}</h2>
                <table class="summary">
                    <tr><th></th><th>{{ __('Total') }} (ghs)</th></tr>
                    <tr class="net"><td>{{ __('Total expenses') }}</td><td class="num">{{ $amount($report['total']) }}</td></tr>
                    <tr class="memo"><td>{{ __('Entries') }}</td><td class="num">{{ $report['count'] }}</td></tr>
                    <tr class="memo"><td>{{ __('Shifts with expenses') }}</td><td class="num">{{ $report['shifts_count'] }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
