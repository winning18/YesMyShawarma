<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Weekly sales report') }}</title>
    <style>
        body { font-family: sans-serif; color: #1a1a1a; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p.subtitle { color: #666; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
        th { background: #f7f7f7; color: #666; font-weight: 600; }
        td.amount, th.amount { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="subtitle">{{ __('Weekly sales report') }} — {{ $summary['start']->format('d/m/Y') }} – {{ $summary['end']->format('d/m/Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Start') }}</th>
                <th>{{ __('End') }}</th>
                <th>{{ __('City') }}</th>
                <th class="amount">{{ __('Orders') }}</th>
                <th class="amount">{{ __('Total') }}</th>
                <th>{{ __('Currency') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $summary['start']->format('d/m/Y') }}</td>
                <td>{{ $summary['end']->format('d/m/Y') }}</td>
                <td>{{ $summary['city'] }}</td>
                <td class="amount">{{ $summary['orders_count'] }}</td>
                <td class="amount">{{ number_format($summary['total'] / 100, 2) }}</td>
                <td>{{ $summary['currency'] }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
