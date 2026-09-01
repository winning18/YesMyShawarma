{{--
    Owner-only (PerformanceController) and deliberately never
    branch-scoped — see PerformanceReportService::visitorTraffic()'s
    docblock for why. Built from visitor_sessions, the same anonymous,
    login-free first-party cookie TrackVisitorSession sets on every
    customer-site request; it has no page-view, referrer, or device data,
    only first-seen/last-seen timestamps and whether a session ever
    converted.
--}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <x-performance.kpi-card
        :label="__('New visits')"
        :value="number_format($traffic['new_visits']['value'])"
        :change-pct="$traffic['new_visits']['change_pct']"
        :highlight="true"
    />
    <x-performance.kpi-card
        :label="__('Returning visits')"
        :value="number_format($traffic['returning_visits']['value'])"
        :change-pct="$traffic['returning_visits']['change_pct']"
    />
    <x-performance.kpi-card
        :label="__('Converted visits')"
        :value="number_format($traffic['converted_visits']['value'])"
        :change-pct="$traffic['converted_visits']['change_pct']"
    />
    <x-performance.kpi-card
        :label="__('Conversion rate')"
        :value="$traffic['conversion']['value'] !== null ? number_format($traffic['conversion']['value'], 1).'%' : '—'"
        :change-pct="$traffic['conversion']['change_pct']"
    />
</div>

@include('dashboard.performance.partials.chart', [
    'labels' => $traffic['chart']['labels'],
    'current' => $traffic['chart']['current'],
    'previous' => $traffic['chart']['previous'],
    'divisor' => 1,
    'unit' => '',
    'ariaLabel' => __('New visits over time, current period vs previous period'),
])

<p class="text-xs text-gray-400 mt-4">
    {{ __("Site-wide across every branch — a visit isn't tied to a specific branch until it converts into an order there.") }}
</p>
