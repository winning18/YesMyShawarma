{{--
    Shared report PDF header: fixed footer line, logo + title on the
    left, business / branch / address / phone on the right, the maroon
    rule and the period. Needs $report (title, branch, general, from,
    to), $address and $logoPath. Page numbers are stamped separately by
    ReportPdfRenderer.
--}}
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
