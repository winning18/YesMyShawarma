<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'today'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        @php
            // Carried into every link on this page so an owner/
            // general_manager drilling into one branch's report from
            // Performance stays locked to it while navigating shifts,
            // dates and channels — never silently drops back to their own
            // ambient session branch mid-browse.
            $branchParam = $viewingBranch ? ['branch' => $viewingBranch->id] : [];
        @endphp

        @if ($viewingBranch)
            <div class="flex items-center justify-between flex-wrap gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3">
                <p class="text-sm text-indigo-800">
                    {{ __('Viewing') }}: <span class="font-semibold">{{ $viewingBranch->name }}</span>
                </p>
                <a href="{{ route('dashboard.performance', ['tab' => 'sales']) }}" class="text-sm font-semibold text-indigo-800 underline shrink-0">
                    {{ __('Back to Performance') }}
                </a>
            </div>
        @endif

        @if ($isCalendarMode)
            {{--
                The explicit secondary lens — "what did this branch sell on
                this specific calendar date" — never the default and never
                to be confused with a shift's own report (a shift crossing
                midnight has no single calendar day that correctly
                represents it; this view only exists for someone who wants
                the calendar-date reading anyway). Channel links carry
                `date` forward so switching POS/Web/Bolt Food stays on the
                same date.
            --}}
            <div x-data="{ preset: '{{ $today->isToday() ? 'today' : ($today->isYesterday() ? 'yesterday' : 'custom') }}' }" class="bg-white shadow rounded-lg p-4 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <form method="GET" action="{{ route('dashboard.reports.today.index') }}" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="channel" value="{{ $channel }}">
                        @if ($viewingBranch)
                            <input type="hidden" name="branch" value="{{ $viewingBranch->id }}">
                        @endif
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1" for="preset">{{ __('Date') }}</label>
                            <select id="preset" x-model="preset" class="rounded-md border-gray-300 text-sm" @change="if (preset !== 'custom') $refs.dateField.value = ''">
                                <option value="today">{{ __('Today') }}</option>
                                <option value="yesterday">{{ __('Yesterday') }}</option>
                                <option value="custom">{{ __('Custom date…') }}</option>
                            </select>
                        </div>
                        <div x-show="preset === 'custom'" x-cloak>
                            <label class="block text-xs font-medium text-gray-500 mb-1" for="date">{{ __('Pick a date') }}</label>
                            <input
                                x-ref="dateField" type="date" id="date" name="date"
                                value="{{ $today->toDateString() }}"
                                max="{{ \Illuminate\Support\Carbon::now('Africa/Accra')->toDateString() }}"
                                class="rounded-md border-gray-300 text-sm"
                            >
                        </div>
                        <template x-if="preset === 'today'">
                            <input type="hidden" name="date" value="{{ \Illuminate\Support\Carbon::now('Africa/Accra')->toDateString() }}">
                        </template>
                        <template x-if="preset === 'yesterday'">
                            <input type="hidden" name="date" value="{{ \Illuminate\Support\Carbon::now('Africa/Accra')->subDay()->toDateString() }}">
                        </template>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Apply') }}</button>
                    </form>

                    <a href="{{ route('dashboard.reports.today.index', [...$branchParam, 'channel' => $channel]) }}" class="text-sm font-semibold text-indigo-600 hover:underline shrink-0">
                        {{ __('Back to current shift') }}
                    </a>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <a
                    href="{{ route('dashboard.reports.today.index', [...$branchParam, 'date' => $today->toDateString(), 'channel' => 'pos']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'pos' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('POS') }}</a>
                <a
                    href="{{ route('dashboard.reports.today.index', [...$branchParam, 'date' => $today->toDateString(), 'channel' => 'web']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'web' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('Web') }}</a>
                <a
                    href="{{ route('dashboard.reports.today.index', [...$branchParam, 'date' => $today->toDateString(), 'channel' => 'bolt_food']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'bolt_food' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('Bolt Food') }}</a>
            </div>
        @else
            {{--
                Shift mode — the default, and the primary way this page is
                used: a shift, not a calendar day, is the atomic sales
                record. $shift is null only when this branch has never had
                one at all yet, in which case there's nothing to navigate
                and the summary below is today's calendar window as a
                coherent fallback (TodayReportController::resolveShiftMode()).
            --}}
            @if ($shift)
                <div class="flex items-center justify-between flex-wrap gap-3 bg-white shadow rounded-lg px-4 py-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <a
                            href="{{ $previousShift ? route('dashboard.reports.today.index', [...$branchParam, 'shift' => $previousShift->id, 'channel' => $channel]) : '#' }}"
                            class="px-2.5 py-1.5 rounded-md shrink-0 {{ $previousShift ? 'text-gray-500 hover:text-gray-800 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed' }}"
                            aria-label="{{ __('Previous shift') }}" @if (! $previousShift) aria-disabled="true" @endif
                        >&larr;</a>

                        <div class="min-w-0">
                            <p class="text-xs text-gray-500">{{ $shift->ended_at ? __('Shift') : __('Current shift') }}</p>
                            <p class="text-sm font-semibold text-gray-800 truncate">
                                {{ $rangeStart->format('d M Y, H:i') }} – {{ $shift->ended_at ? $rangeEnd->format('d M Y, H:i') : __('now') }}
                            </p>
                        </div>

                        <a
                            href="{{ $nextShift ? route('dashboard.reports.today.index', [...$branchParam, 'shift' => $nextShift->id, 'channel' => $channel]) : '#' }}"
                            class="px-2.5 py-1.5 rounded-md shrink-0 {{ $nextShift ? 'text-gray-500 hover:text-gray-800 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed' }}"
                            aria-label="{{ __('Next shift') }}" @if (! $nextShift) aria-disabled="true" @endif
                        >&rarr;</a>
                    </div>

                    <a href="{{ route('dashboard.reports.today.index', [...$branchParam, 'date' => $today->toDateString(), 'channel' => $channel]) }}" class="text-sm font-semibold text-indigo-600 hover:underline shrink-0">
                        {{ __('View by calendar date') }}
                    </a>
                </div>
            @else
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                    <p class="text-sm text-amber-800">{{ __('No shifts recorded yet — showing today\'s calendar totals instead.') }}</p>
                </div>
            @endif

            @php
                $shiftParams = [...$branchParam, ...($shift ? ['shift' => $shift->id] : ['date' => $today->toDateString()])];
            @endphp
            <div class="flex items-center justify-end gap-2">
                <a
                    href="{{ route('dashboard.reports.today.index', [...$shiftParams, 'channel' => 'pos']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'pos' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('POS') }}</a>
                <a
                    href="{{ route('dashboard.reports.today.index', [...$shiftParams, 'channel' => 'web']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'web' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('Web') }}</a>
                <a
                    href="{{ route('dashboard.reports.today.index', [...$shiftParams, 'channel' => 'bolt_food']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'bolt_food' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('Bolt Food') }}</a>
            </div>
        @endif

        {{-- Daily financial summary --}}
        <div class="bg-white shadow rounded-lg p-4">
            <p class="text-xs text-gray-500">{{ __('Total sales') }} ({{ $channel === 'bolt_food' ? __('BOLT FOOD') : strtoupper($channel) }})</p>
            <p class="text-2xl font-bold text-gray-800">GH₵{{ number_format($summary['total_sales'] / 100, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __(':count orders', ['count' => $summary['orders_count']]) }}</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 mt-3 border-t border-gray-100">
                @forelse ($summary['by_payment_method'] as $method => $amount)
                    <div>
                        <p class="text-xs text-gray-500 capitalize">{{ $method }}</p>
                        <p class="text-sm font-semibold text-gray-800">GH₵{{ number_format($amount / 100, 2) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('No sales recorded in this window.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Shifts today — one row per branch per open/closed till session,
             not per staff member (orders.md's Shifts section): "Opened by"
             and "Closed by" can be different people, since any staff
             working the branch while it's open can end it. total_sales is
             the figure entered by whoever closed it (required of everyone
             now), system_sales is the snapshot of what the system had
             recorded for that shift at that exact moment (see
             ShiftController::end()). Anything entered above it shows here
             as "Extra" rather than getting silently dropped.

             Expenses: the sum of that shift's shift_expenses rows. "—"
             (never recorded — predates this feature) is kept visually
             distinct from a genuine GH₵0.00 (no_expenses explicitly
             confirmed) rather than collapsing both into the same blank.
             Net is total_sales minus expenses, shown as-is rather than
             floored at zero — expenses exceeding sales is a real signal
             worth seeing, not hiding. --}}
        @if ($shifts->isNotEmpty())
            <section class="space-y-2">
                <h3 class="font-semibold text-gray-800 uppercase text-sm tracking-wide">{{ __('Shifts that day') }}</h3>
                <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('Date') }}</th>
                                <th class="px-4 py-2">{{ __('Opened by') }}</th>
                                <th class="px-4 py-2">{{ __('Closed by') }}</th>
                                <th class="px-4 py-2">{{ __('Started') }}</th>
                                <th class="px-4 py-2">{{ __('Ended') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Total sales') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('System sales') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Extra') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Expenses') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Net') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($shifts as $row)
                                @php
                                    $extra = ($row->total_sales !== null && $row->system_sales !== null)
                                        ? max(0, $row->total_sales - $row->system_sales)
                                        : null;
                                    $expensesTotal = $row->expenses->sum('amount');
                                    $hasExpenseRecord = $row->no_expenses || $row->expenses->isNotEmpty();
                                    $net = ($row->total_sales !== null && $hasExpenseRecord)
                                        ? $row->total_sales - $expensesTotal
                                        : null;
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ $row->started_at->timezone('Africa/Accra')->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-800">{{ $row->user->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row->endedBy->name ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $row->started_at->timezone('Africa/Accra')->format('H:i') }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        @if ($row->ended_at)
                                            {{ $row->ended_at->timezone('Africa/Accra')->format('H:i') }}
                                            @if (! $row->ended_at->timezone('Africa/Accra')->isSameDay($row->started_at->timezone('Africa/Accra')))
                                                <span class="text-xs text-amber-600 font-medium" title="{{ __('Ended the next day') }}">{{ __('+1') }}</span>
                                            @endif
                                        @else
                                            {{ __('Active') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-800">
                                        {{ $row->total_sales !== null ? 'GH₵'.number_format($row->total_sales / 100, 2) : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-500">
                                        {{ $row->system_sales !== null ? 'GH₵'.number_format($row->system_sales / 100, 2) : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium {{ $extra ? 'text-green-700' : 'text-gray-400' }}">
                                        {{ $extra ? 'GH₵'.number_format($extra / 100, 2) : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-500">
                                        {{ $hasExpenseRecord ? 'GH₵'.number_format($expensesTotal / 100, 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium {{ $net !== null && $net < 0 ? 'text-red-600' : 'text-gray-800' }}">
                                        {{ $net !== null ? 'GH₵'.number_format($net / 100, 2) : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{--
                                            Shift mode is keyed by this
                                            shift's own id, not a
                                            reconstructed from/to window — a
                                            shift running 6pm-2am shows as
                                            one complete report instead of
                                            being split (or half-lost)
                                            across two different calendar
                                            days, and the id also drives
                                            Previous/Next-shift navigation
                                            from the linked page
                                            (TodayReportController,
                                            ShiftService::before()/after()).
                                        --}}
                                        <a
                                            href="{{ route('dashboard.reports.today.index', [...$branchParam, 'shift' => $row->id, 'channel' => $channel]) }}"
                                            class="text-indigo-600 hover:underline whitespace-nowrap"
                                        >{{ __('View full report') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Category sections --}}
        @forelse ($summary['categories'] as $group)
            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 uppercase text-sm tracking-wide">{{ $group['category'] }}</h3>
                    <span class="text-sm text-gray-500">GH₵{{ number_format($group['subtotal'] / 100, 2) }}</span>
                </div>
                <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('Item') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Qty') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Unit') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($group['items'] as $line)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800">{{ $line['name'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ $line['qty'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">GH₵{{ number_format($line['unit'] / 100, 2) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-800 font-medium">GH₵{{ number_format($line['total'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="text-sm text-gray-500">{{ __('No sales recorded in this window.') }}</p>
        @endforelse

        {{-- Modifiers --}}
        @if ($summary['modifiers']['items']->isNotEmpty())
            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 uppercase text-sm tracking-wide">{{ __('Modifiers') }}</h3>
                    <span class="text-sm text-gray-500">GH₵{{ number_format($summary['modifiers']['subtotal'] / 100, 2) }}</span>
                </div>
                <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('Item') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Qty') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Unit') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($summary['modifiers']['items'] as $line)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800">{{ $line['name'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ $line['qty'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">GH₵{{ number_format($line['unit'] / 100, 2) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-800 font-medium">GH₵{{ number_format($line['total'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
