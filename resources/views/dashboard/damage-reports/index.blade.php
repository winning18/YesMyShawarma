<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Damage Reports') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            @error('damage_report')
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>
            @enderror

            {{--
                Filing is staff-only here (damage_reports.file) — a rider
                files their own order-specific report from the rider
                dashboard's own modal instead (DamageReportPolicy::create()).
            --}}
            @can('create', \App\Models\DamageReport::class)
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-3">{{ __('Report a damaged item') }}</h3>
                    <form method="POST" action="{{ route('dashboard.damage-reports.store') }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf

                        <div>
                            <label for="description" class="block text-xs font-medium text-gray-500 mb-1">{{ __('What happened') }} <span class="text-red-600">*</span></label>
                            <textarea id="description" name="description" rows="2" required maxlength="1000"
                                class="w-full rounded-md border-gray-300 text-sm"
                                placeholder="{{ __('e.g. Box of 10 Shawarma wraps got wet during delivery and had to be thrown out.') }}"
                            >{{ old('description') }}</textarea>
                        </div>

                        @if ($stockItems->isNotEmpty())
                            <div>
                                <label for="stock_item_id" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Stock item (optional)') }}</label>
                                <select id="stock_item_id" name="stock_item_id" class="rounded-md border-gray-300 text-sm">
                                    <option value="">{{ __("Not a stock item (e.g. equipment)") }}</option>
                                    @foreach ($stockItems as $item)
                                        <option value="{{ $item->id }}" @selected(old('stock_item_id') == $item->id)>{{ $item->name }} ({{ $item->unit }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <label for="photo" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Photo') }} <span class="text-red-600">*</span></label>
                            <input id="photo" type="file" name="photo" accept="image/*" required class="text-sm">
                            <p class="text-xs text-gray-400 mt-1">{{ __('A photo is required — this is the evidence a reviewer checks against.') }}</p>
                        </div>

                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                            {{ __('Submit report') }}
                        </button>
                    </form>
                </div>
            @endcan

            <div class="bg-white shadow rounded-lg p-4">
                <form method="GET" action="{{ route('dashboard.damage-reports.index') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                        <select id="status" name="status" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('Any status') }}</option>
                            @foreach ($statuses as $value)
                                <option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Apply filters') }}
                    </button>
                </form>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Branch') }}</th>
                            <th class="px-4 py-2">{{ __('Order / item') }}</th>
                            <th class="px-4 py-2">{{ __('Description') }}</th>
                            <th class="px-4 py-2">{{ __('Photo') }}</th>
                            <th class="px-4 py-2">{{ __('Reported by') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th>
                            <th class="px-4 py-2">{{ __('Reported') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($reports as $report)
                            @php
                                $badgeClass = match ($report->status) {
                                    'approved' => 'bg-green-50 text-green-700',
                                    'denied' => 'bg-red-50 text-red-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr>
                                <td class="px-4 py-2 text-gray-500">{{ $report->branch->name }}</td>
                                <td class="px-4 py-2 text-gray-500">
                                    @if ($report->order)
                                        <a href="{{ route('dashboard.orders.show', $report->order) }}" class="text-indigo-600 hover:underline">{{ $report->order->reference }}</a>
                                    @elseif ($report->stockItem)
                                        {{ $report->stockItem->name }}
                                    @else
                                        <span class="text-gray-400">{{ __('—') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-500 max-w-xs truncate" title="{{ $report->description }}">{{ $report->description }}</td>
                                <td class="px-4 py-2">
                                    @if ($report->photo_path)
                                        <a href="{{ route('dashboard.damage-reports.photo', $report) }}" target="_blank" class="text-indigo-600 hover:underline">{{ __('View') }}</a>
                                    @else
                                        <span class="text-gray-400 text-xs">{{ __('Expired') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-500">
                                    {{ $report->reportedBy?->name ?? __('Deleted account') }}
                                    <span class="text-gray-400">({{ $report->reporter_role }})</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-xs font-medium px-2 py-1 rounded-md capitalize {{ $badgeClass }}">{{ $report->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $report->created_at->timezone('Africa/Accra')->format('d M Y, H:i') }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if ($report->status === 'pending')
                                        <div class="flex items-center justify-end gap-2">
                                            @can('approve', $report)
                                                <form method="POST" action="{{ route('dashboard.damage-reports.approve', $report) }}">
                                                    @csrf
                                                    <button type="submit" class="text-green-700 hover:underline">{{ __('Approve') }}</button>
                                                </form>
                                            @endcan
                                            @can('deny', $report)
                                                <form method="POST" action="{{ route('dashboard.damage-reports.deny', $report) }}"
                                                    onsubmit="return confirm({{ Js::from(__('Deny this damage report?')) }})">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Deny') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ __('No damage reports match these filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $reports->links() }}
        </div>
    </div>
</x-app-layout>
